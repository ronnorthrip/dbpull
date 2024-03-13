# A laravel package for pulling remote data to use for local development

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ronnorthrip/dbpull.svg?style=flat-square)](https://packagist.org/packages/ronnorthrip/dbpull)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ronnorthrip/dbpull/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ronnorthrip/dbpull/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ronnorthrip/dbpull/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ronnorthrip/dbpull/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ronnorthrip/dbpull.svg?style=flat-square)](https://packagist.org/packages/ronnorthrip/dbpull)

Pull new or updated data from a remote database.

## Installation

You can install the package via composer:

```bash
composer require ronnorthrip/dbpull
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag=dbpull-config
```


## Usage

The db:pull command pulls the remote database and imports it into your local database. 
Currently, the command currently works with MySQL databases and over passwordless SSH.
The database is pulled into a temporary file locally in database/pulls and state is persisted in .dbpull.json.
You should add both of these to your .gitignore.

```bash
php artisan db:pull
```

### Compatible Databases

MySQL is the only database currently supported.

### How It Works

This package works my keeping a snapshot of the max ids and max timestamps for each table in the remote database in 
the local .dbpull.json file. When you run the command, it compares the remote database with the snapshot and pulls any 
records where the id or timestamp is greater than the snapshot values. It does this in two passes, first pulling new 
records and then updating records. It **WILL SOON** check for deleted records and deletes them locally too.

It was specifically designed to work with large databases and to be able to pull only the records that have changed.
It's been honed over years of use with databases with millions of records and tons of tables.
And this approach is much faster than pulling the entire database each time.
Of course as you dev locally you'll be creating and editing records, so sometimes its helpful to pull a full dump.
All you have to do is add the **--full-dump** flag to the command to do so.

### Parameters

You can pass additional flags to the command to skip certain steps or alter other behavior.

```php
--table=*        : limit which table or tables to pull - can be multiple
--replace        : entirely replace the local data with the pulled data
--no-table-skips : dont skip any tables when pulling - normally migrations, jobs, failed_jobs
--ping           : ping the remote database to verify your connection config
--dry-run        : check what changes need to be pulled without executing them
--skip-updates   : pull new records using ids without checking for updated rows
--skip-deletes   : pull new records using ids without checking for deleted rows
--force          : force the pull without comparing the migrations or table lists
--full-dump      : perform a full dump from remote replacing local and without skipping anything
```

## Config

### Environment Variables

The package is highly configurable, and we've set reasonable defaults where possible. 
You'll need to at least configure access to your remote database in your .env file.

```php
DBPULL_PRODUCTION_DB_DATABASE=
DBPULL_PRODUCTION_DB_USERNAME=
DBPULL_PRODUCTION_DB_PASSWORD=
DBPULL_PRODUCTION_SSH=
```

### Processing Configuration

### Remote Configuration

### Adding Remotes

Duplicate the production config block and give it a different name to add additional remotes, and add the relevant env vars.

```php
    /* staging */
    'staging' => [
        ...
    ],
```

Then when you run the command, you can specify which remote to pull from:

```bash
php artisan db:pull staging
```

### Config File

This is the contents of the published config file:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Pull Data from a Remote Database
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration and credentials used when pulling
    | database content from a remote database directly or via ssh connections.
    |
    */

    /* dbpull configuration */
    'config' => [
        'snapshot_file' => env('DBPULL_SNAPSHOT_FILE', '.dbpull.json'),
        'default_db_type' => env('DBPULL_DEFAULT_DB_TYPE', 'mysql'),
        'default_remote' => env('DBPULL_DEFAULT_REMOTE', 'production'),
        'skip_tables' => env('DBPULL_SKIP_TABLES', 'failed_jobs , jobs , migrations'),
        'skip_migrations_check' => env('DBPULL_SKIP_MIGRATIONS_CHECK', false),
        'ids_only' => env('DBPULL_IDS_ONLY', false),
        'ids_only_tables' => env('DBPULL_IDS_ONLY_TABLES', false),
        'ids_only_tables_prefix' => env('DBPULL_IDS_ONLY_TABLES_PREFIX', false),
        'skip_updates' => env('DBPULL_SKIP_UPDATES', false),
        'skip_updates_tables' => env('DBPULL_SKIP_UPDATES_TABLES'),
        'skip_updates_tables_prefix' => env('DBPULL_SKIP_UPDATES_TABLES_PREFIX'),
        'skip_deletes' => env('DBPULL_SKIP_DELETES', false),
        'skip_deletes_tables' => env('DBPULL_SKIP_DELETES_TABLES'),
        'skip_deletes_tables_prefix' => env('DBPULL_SKIP_DELETES_TABLES_PREFIX'),
        'executables' =>[
            'mysql' => ['cli' => 'mysql', 'dump' => 'mysqldump'],
        ],
    ],

    /* local - uses the standard laravel envs */
    'local' => [
        'type' => env('DB_TYPE', env('DBPULL_DEFAULT_DB_TYPE', 'mysql')),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'base_path' => env('DBPULL_LOCAL_BASE_PATH', base_path()),
        'migrations_path' => env('DBPULL_LOCAL_MIGRATIONS_PATH', database_path('migrations')),
        'pulls_path' => env('DBPULL_LOCAL_PULLS_PATH', database_path('pulls')),
        'executable_cli' => env('DBPULL_LOCAL_EXECUTABLE_CLI'),
        'executable_dump' => env('DBPULL_LOCAL_EXECUTABLE_DUMP'),
    ],

    /* production */
    'production' => [
        'connection' => env('DBPULL_PRODUCTION_CONNECTION', 'ssh'), // remote or ssh
        'type' => env('DBPULL_PRODUCTION_DB_TYPE', env('DBPULL_DEFAULT_DB_TYPE', 'mysql')),
        'host' => env('DBPULL_PRODUCTION_DB_HOST', '127.0.0.1'),
        'port' => env('DBPULL_PRODUCTION_DB_PORT', '3306'),
        'database' => env('DBPULL_PRODUCTION_DB_DATABASE'),
        'username' => env('DBPULL_PRODUCTION_DB_USERNAME'),
        'password' => env('DBPULL_PRODUCTION_DB_PASSWORD'),
        'base_path' => env('DBPULL_PRODUCTION_BASE_PATH'),
        'migrations_path' => env('DBPULL_PRODUCTION_MIGRATIONS_PATH', 'database/migrations'),
        'ssh' => env('DBPULL_PRODUCTION_SSH'),
        'executable_cli' => env('DBPULL_PRODUCTION_EXECUTABLE_CLI'),
        'executable_dump' => env('DBPULL_PRODUCTION_EXECUTABLE_DUMP'),
    ],

    /* copy the block above to add additional remote source */
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Spaitie

This package was templated from Spatie's package skeleton at https://github.com/spatie/package-skeleton-laravel

## Credits

- [Ron Northrip](https://github.com/ronnorthrip)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
