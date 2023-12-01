<?php

namespace RonNorthrip\DBPull\Commands;

use App;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;

class DBPull extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:pull {from?} {--table=*} {--replace} '.
        '{--no-skips} {--ping} {--dry-run} {--skip-updates} {--skip-deletes}'.
        ' {--force} {--full-dump}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull data from a remote mysql database over ssh';

    public const SUCCESS = 0;

    public const FAILURE = 1;

    public const INVALID = 2;

    protected $remote_migrations_path = '';

    protected $remote_app_path = '';

    protected $local_dbpull_file = '';

    protected $local_migrations_path = '';

    protected $local_pulls_path = '';

    protected $skip_tables = ['failed_jobs', 'jobs', 'migrations'];

    protected $remote_ssh = '';

    protected $remote_mysql_host = '';

    protected $remote_mysql_port = '';

    protected $remote_mysql_database = '';

    protected $remote_mysql_user = '';

    protected $remote_mysql_password = '';

    protected $remote_mysql_auth_echo = '';

    protected $local_mysql_database = '';

    protected $local_mysql_user = '';

    protected $local_mysql_password = '';

    protected $local_mysql_auth_echo = '';

    protected $from = '';

    protected $from_lc = '';

    protected $from_uc = '';

    protected $data = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Collect vars and check config to setup for processing.
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->from = $this->argument('from') ?? config('dbpull.config.default_remote');
        if (! config('dbpull.'.$this->from)) {
            $this->error('The "'.$this->from.'" remote is not configured in config/dbpull.php');

            return false;
        }
        $this->from_lc = strtolower($this->from);
        $this->from_uc = strtoupper($this->from);
        $this->local_dbpull_file = base_path(config('dbpull.config.snapshot_file'));
        $this->read_data();
        $this->move_old_data();
        $this->remote_app_path = config('dbpull.'.$this->from.'.base_path', '');
        $this->remote_migrations_path = config('dbpull.'.$this->from.'.migrations_path', '');
        $this->local_migrations_path = config('dbpull.local.migrations_path', '');
        $this->local_pulls_path = config('dbpull.local.pulls_path', '');
        if (! str_ends_with($this->local_pulls_path, '/')) {
            $this->local_pulls_path .= '/';
        }
        if (! file_exists($this->local_pulls_path)) {
            mkdir($this->local_pulls_path);
        }
        $this->local_mysql_user = config('dbpull.local.username', '');
        $this->local_mysql_password = config('dbpull.local.password', '');
        $this->local_mysql_database = config('dbpull.local.database', '');
        $this->local_mysql_auth_echo = "echo -e \"[client]\nuser=$this->local_mysql_password\npassword=$this->local_mysql_database\"";
        $this->remote_ssh = config('dbpull.'.$this->from.'.ssh', '');
        $this->remote_mysql_host = config('dbpull.'.$this->from.'.host', '');
        $this->remote_mysql_port = config('dbpull.'.$this->from.'.port', '');
        $this->remote_mysql_user = config('dbpull.'.$this->from.'.username', '');
        $this->remote_mysql_password = config('dbpull.'.$this->from.'.password', '');
        $this->remote_mysql_database = config('dbpull.'.$this->from.'.database', '');
        $this->remote_mysql_auth_echo = "echo -e \"[client]\nuser=$this->remote_mysql_user\npassword=$this->remote_mysql_password\"";
        if (config('dbpull.config.skip_tables')) {
            $skip_these = explode(',', config('dbpull.config.skip_tables'));
            $skip_these = array_map('trim', $skip_these);
            $this->skip_tables = $skip_these;
        }
        if (
            ($this->local_dbpull_file == '') ||
            ($this->remote_app_path == '') ||
            ($this->remote_migrations_path == '') ||
            ($this->local_migrations_path == '') ||
            ($this->local_pulls_path == '') ||
            ($this->local_mysql_user == '') ||
            ($this->local_mysql_password == '') ||
            ($this->local_mysql_database == '') ||
            ($this->remote_ssh == '') ||
            ($this->remote_mysql_user == '') ||
            ($this->remote_mysql_password == '') ||
            ($this->remote_mysql_database == '')
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (App::environment('production')) {
            $this->error('This server IS PRODUCTION. So NOPE.');

            return self::INVALID;
        }
        if (! $this->setup()) {
            $this->error('A required env setting or config is missing.');

            return self::INVALID;
        }
        if (! $this->ssh_ping()) {
            $this->error('Could not connect to remote server via ssh');

            return self::FAILURE;
        }
        if ($this->option('ping')) {
            $this->info('PING!');
            $this->info('Connect to remote server via ssh worked.');
            if ($this->ssh_ping_mysql()) {
                $this->info('Database connection on remote server worked.');
            } else {
                $this->error('Database connection on remote server failed.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }
        $full_dump = $this->option('full-dump');
        $replace = $full_dump || $this->option('replace');
        if ($replace) {
            if (! $this->confirm('Are you sure you want to REPLACE the local data?')) {
                return self::SUCCESS;
            }
        }
        $force = $full_dump || $this->option('force');
        if (! $force) {
            if ($this->ssh_count_pending_migrations() > 0) {
                $this->error('Production app has pending migrations. Please apply migrations before proceeding.');

                return self::INVALID;
            }
            if ($this->local_count_pending_migrations() > 0) {
                $this->error('Local app has pending migrations. Please apply migrations before proceeding.');

                return self::INVALID;
            }
            if ($this->ssh_count_migrations() !== $this->local_count_migrations()) {
                $this->error('Local and remote migrations do not match. Data structure differences wont work here');

                return self::INVALID;
            }
        }
        $tables = $this->option('table');
        if ($tables) {
            $missing = array_diff($tables, $this->ssh_get_tables());
            if ($missing) {
                $this->error('Missing '.count($missing).' table(s) from remote database. '.implode(', ', $missing));

                return self::INVALID;
            }
            $missing = array_diff($tables, $this->local_get_tables());
            if ($missing) {
                $this->error('Missing '.count($missing).' table(s) from local database. '.implode(', ', $missing));

                return self::INVALID;
            }
        }
        $this->info('Production Server Pinged and Ready.');
        $action = ($this->option('dry-run')) ? 'Dry Run' : 'Pull';
        $this->info("Starting $action.");
        if (! $this->option('dry-run')) {
            $this->local_flush_pulls_dir();
        }

        if ($full_dump) {
            $tables = $this->ssh_get_tables();
        } elseif (! $tables) {
            $tables = ($force) ?
                array_intersect($this->ssh_get_tables(), $this->local_get_tables()) :
                $this->local_get_tables();
        }
        $id_tables = [];
        $open_tables = [];

        $no_skips = $full_dump || $this->option('no-skips');
        $skip_tables = ($no_skips) ? [] : $this->skip_tables;
        foreach ($tables as $table) {
            if (! in_array($table, $skip_tables)) {
                if ($replace) {
                    $open_tables[$table] = 0;
                } elseif (Schema::hasColumn($table, 'id')) {
                    $id_tables[$table] = 1 * DB::select('select ifnull(max(id), 0) as id from '.$table)[0]->id;
                } else {
                    $open_tables[$table] = 1 * DB::select('select ifnull(count(*), 0) as count from '.$table)[0]->count;
                }
            }
        }

        foreach ($id_tables as $table => $max) {
            $this->info("Checking $table");
            $this->set_data_array($table, $this->ssh_table_maxes($table));
            $ssh_id_max = $this->get_data($table, 'id');
            $diff = $ssh_id_max - $max;
            if ($ssh_id_max < $max) {
                $diff = abs($diff);
                $this->comment("Local $table has more data than remote does ($max:$ssh_id_max)");
                $action = ($this->option('dry-run')) ? 'Should Truncate' : 'Truncating';
                $this->comment("$action $diff rows from local $table");
                if (! $this->option('dry-run')) {
                    DB::delete("delete from $table where id > $ssh_id_max");
                }
            } elseif ($ssh_id_max > $max) {
                $action = ($this->option('dry-run')) ? 'Needs' : 'Pulling';
                $this->info("$action $diff rows from $table");
                if (! $this->option('dry-run')) {
                    $this->ssh_table_dump_new_id_rows($table, $max);
                }
            }
            if ($this->has_old_data()) {
                $count = $this->ssh_table_dump_updated_id_rows($table, $this->option('dry-run'));
                $action = ($this->option('dry-run')) ? 'Should Update' : 'Updating';
                $this->info("$action $count rows from local $table");
            }
            /* too slow
            if (!$this->option('skip-deletes')) {
                $action = ($this->option('dry-run'))? 'Should Delete' : 'Deleting';
                if (!$this->option('dry-run')) {
                    $count = $this->ssh_table_dump_deleted_id_rows($table, $max);
                    $this->info("$action $count blocks from $table");
                }
            }
            */
        }

        foreach ($open_tables as $table => $count) {
            $this->info("Checking $table");
            $ssh_table_count = $this->ssh_table_count($table);
            $diff = $ssh_table_count - $count;
            if ($ssh_table_count < $count) {
                $this->error("Local $table has more data than remote does ($count:$ssh_table_count)");
            } elseif ($ssh_table_count > $count) {
                $action = ($this->option('dry-run')) ? 'Needs' : 'Pulling';
                $this->info("$action full dump from $table");
                if (! $this->option('dry-run')) {
                    $this->ssh_table_dump_full($table);
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry Run Done.');

            return self::SUCCESS;
        }

        if ($full_dump) {
            $this->local_drop_all_tables();
        }

        $this->info('Importing data pulls.');
        if ($handle = opendir($this->local_pulls_path)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' && $file != '..' && substr($file, 0, 1) != '.') {
                    $this->info("Importing $file");
                    $this->local_sql_import($file);
                }
            }
            closedir($handle);
        }

        $this->info('Done.');
        $this->remove_old_data();
        $this->write_data();
        $this->local_flush_pulls_dir();

        return self::SUCCESS;
    }

    public function local_tables_key()
    {
        return array_keys(get_object_vars(DB::select('SHOW TABLES')[0]))[0];
    }

    public function local_get_tables()
    {
        return Arr::pluck(DB::select('SHOW TABLES'), $this->local_tables_key());
    }

    public function local_drop_all_tables()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->local_get_tables() as $drop_table) {
            Schema::drop($drop_table);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function ssh_get_tables()
    {
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"SHOW TABLES\" '", $result);

        return $result;
    }

    public function ssh_ping()
    {
        exec("ssh $this->remote_ssh 'whoami'", $result);

        return implode("\n", $result) !== '';
    }

    public function ssh_ping_mysql()
    {
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"SHOW TABLES\" '", $result);

        return implode("\n", $result) !== '';
    }

    public function ssh_count_migrations()
    {
        exec("ssh $this->remote_ssh 'ls -1 $this->remote_migrations_path | wc -l'", $result);

        return 1 * implode('', $result);
    }

    public function ssh_count_pending_migrations()
    {
        exec("ssh $this->remote_ssh 'cd $this->remote_app_path;php artisan migrate:status' ", $result);
        // CAUTION - minor array slice difference from local_count_pending_migrations
        $lines = array_slice($result, 3, -1);
        $matches = preg_grep('/^\\| N\s+\\|/i', $lines);

        return count($matches);
    }

    public function ssh_table_maxes($table)
    {
        $maxes = [];
        $select = 'select ifnull(max(id), 0) as max_id';
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"$select from $table\" '", $result);
        $id = 1 * implode('', $result);
        $maxes = ['id' => $id];

        $selects = [];
        $columns = [];
        $result = null;
        if (Schema::hasColumn($table, 'created_at')) {
            $selects[] = 'ifnull(max(created_at), 0) as max_created_at';
            $columns[] = 'created_at';
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $selects[] = 'ifnull(max(updated_at), 0) as max_updated_at';
            $columns[] = 'updated_at';
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $selects[] = 'ifnull(max(deleted_at), 0) as max_deleted_at';
            $columns[] = 'deleted_at';
        }
        if ((count($selects) > 0) && (! $this->option('skip-updates'))) {
            $select = 'select '.implode(', ', $selects);
            exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"$select from $table where id <= $id\" '", $result);
            if ($result) {
                foreach (explode("\t", trim($result[0])) as $i => $max) {
                    $maxes[$columns[$i]] = $max;
                }
            }
        }

        return $maxes;
    }

    public function ssh_table_count($table)
    {
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"select ifnull(count(*), 0) as count from $table\" '", $result);

        return 1 * implode('', $result);
    }

    public function ssh_table_dump_new_id_rows($table, $max)
    {
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysqldump --defaults-extra-file=/dev/stdin --replace --skip-create-options --skip-add-drop-table --no-create-info -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database $table --where \"id > $max\" ' > ".$this->local_pulls_path.$table.'.sql');
    }

    public function ssh_table_dump_updated_id_rows($table, $justcount)
    {
        if (! $this->has_old_data()) {
            return;
        }
        if ($this->option('skip-updates')) {
            return;
        }
        $id = $this->get_old_data($table, 'id');
        if ($id === null) {
            return;
        }
        $wheres = [];
        $columns = ['created_at', 'updated_at', 'deleted_at'];
        $result = null;
        foreach ($columns as $col) {
            if (Schema::hasColumn($table, $col)) {
                $date = $this->get_old_data($table, $col);
                if ($date) {
                    $wheres[] = $col.' > "'.$date.'"';
                }
                $columns[] = $col;
            }
        }

        $count = 0;
        if (count($wheres) > 0) {
            $where = "id <= $id AND (".implode(' OR ', $wheres).')';
            $select = 'select count(id) as count ';
            exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"$select from $table where ".addslashes($where)."\" '", $result);
            if (! $result) {
                return 0;
            }
            $count = 1 * implode('', $result);
            if ($count === 0) {
                return 0;
            }
            if ($justcount) {
                return $count;
            }

            exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysqldump --defaults-extra-file=/dev/stdin --replace --skip-create-options --skip-add-drop-table --no-create-info -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database $table --where \"".addslashes($where)."\" ' > ".$this->local_pulls_path.$table.'.updates.sql');

        }

        return $count;
    }

    public function ssh_table_dump_deleted_id_rows($table, $max)
    {
        $max = $max;
        $count = 0;
        if ($this->option('skip-deletes')) {
            return;
        }
        $select =
            'SELECT a.id+1 AS start, MIN(b.id) - 1 AS end '.
            "FROM $table AS a, $table AS b ".
            "WHERE a.id < b.id and a.id <= $max ".
            'GROUP BY a.id '.
            'HAVING start < MIN(b.id) ';
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin -B -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database --disable-column-names -e \"$select\" ' > ".$this->local_pulls_path.$table.'.deletes.sql');
        if (file_exists($this->local_pulls_path.$table.'.deletes.sql')) {
            $content = file_get_contents($this->local_pulls_path.$table.'.deletes.sql');
            $deletes = '';
            foreach (explode("\n", $content) as $line) {
                $cols = explode("\t", trim($line));
                if (count($cols) == 2) {
                    $deletes .= "DELETE from $table WHERE id >= $cols[0] and id <= $cols[1];\n";
                    $count++;
                }
            }
            if ($deletes === '') {
                unlink($this->local_pulls_path.$table.'.deletes.sql');
            } else {
                file_put_contents($this->local_pulls_path.$table.'.deletes.sql', $deletes);
            }
        }

        return $count;
    }

    public function ssh_table_dump_full($table)
    {
        exec("ssh $this->remote_ssh '$this->remote_mysql_auth_echo | mysqldump --defaults-extra-file=/dev/stdin -h $this->remote_mysql_host -P $this->remote_mysql_port $this->remote_mysql_database $table' > ".$this->local_pulls_path.$table.'.sql');
    }

    public function local_count_migrations()
    {
        exec("ls -1 $this->local_migrations_path | wc -l", $result);

        return 1 * implode('', $result);
    }

    public function local_count_pending_migrations()
    {
        $output = new BufferedOutput;
        Artisan::call('migrate:status', [], $output);
        $lines = array_slice(explode("\n", $output->fetch()), 3, -2);
        $matches = preg_grep('/^\\| N\s+\\|/i', $lines);

        return count($matches);
    }

    public function local_flush_pulls_dir()
    {
        exec('rm -r '.$this->local_pulls_path.'*');
    }

    public function local_sql_import($file)
    {
        #TODO - this is a hack to get around the mysql auth echo not working
        # exec("$this->local_mysql_auth_echo | mysql --defaults-extra-file=/dev/stdin $this->local_mysql_database < ".$this->local_pulls_path.$file);
        exec("mysql -u$this->local_mysql_user -p$this->local_mysql_password $this->local_mysql_database < ".$this->local_pulls_path.$file);
    }

    public function has_old_data()
    {
        $from = 'old_'.$this->from_lc;
        if (array_key_exists($from, $this->data)) {
            return true;
        }

        return false;
    }

    public function get_data($table, $col)
    {
        $from = $this->from_lc;
        if (! array_key_exists($from, $this->data)) {
            return null;
        }
        if (! array_key_exists($table, $this->data[$from])) {
            return null;
        }
        if (! array_key_exists($col, $this->data[$from][$table])) {
            return null;
        }

        return $this->data[$from][$table][$col];
    }

    public function get_old_data($table, $col)
    {
        $from = 'old_'.$this->from_lc;
        if (! array_key_exists($from, $this->data)) {
            return null;
        }
        if (! array_key_exists($table, $this->data[$from])) {
            return null;
        }
        if (! array_key_exists($col, $this->data[$from][$table])) {
            return null;
        }

        return $this->data[$from][$table][$col];
    }

    public function set_data($table, $col, $val)
    {
        $from = $this->from_lc;
        if (! array_key_exists($from, $this->data)) {
            $this->data[$from] = [];
        }
        if (! array_key_exists($table, $this->data[$from])) {
            $this->data[$from][$table] = [];
        }
        $this->data[$from][$table][$col] = $val;
    }

    public function set_data_array($table, $array)
    {
        $from = $this->from_lc;
        if (! array_key_exists($from, $this->data)) {
            $this->data[$from] = [];
        }
        if (! array_key_exists($table, $this->data[$from])) {
            $this->data[$from][$table] = [];
        }
        $this->data[$from][$table] = $array;
    }

    public function read_data()
    {
        $file = $this->local_dbpull_file;
        if (! file_exists($file)) {
            return;
        }
        $this->data = json_decode(file_get_contents($file), true);
    }

    public function move_old_data()
    {
        $from = $this->from_lc;
        if (! array_key_exists($from, $this->data)) {
            return;
        }
        $this->data['old_'.$from] = $this->data[$from];
        unset($this->data[$from]);
    }

    public function remove_old_data()
    {
        $from = 'old_'.$this->from_lc;
        if (! array_key_exists($from, $this->data)) {
            return;
        }
        unset($this->data[$from]);
    }

    public function write_data()
    {
        $file = $this->local_dbpull_file;
        file_put_contents($file, json_encode($this->data, JSON_PRETTY_PRINT));
    }
}
