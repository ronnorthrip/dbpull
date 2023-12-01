<?php

namespace RonNorthrip\DBPull\Commands;

use Illuminate\Console\Command;

class DBPull extends Command
{
    public $signature = 'dbpull';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
