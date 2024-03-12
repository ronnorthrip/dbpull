<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pull Data from a Remote Database
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration and credentials used when pulling
    | database content from a remote mysql database over ssh connections.
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
    ],

    /* production */
    'production' => [
        'type' => env('DBPULL_PRODUCTION_DB_TYPE', env('DBPULL_DEFAULT_DB_TYPE', 'mysql')),
        'host' => env('DBPULL_PRODUCTION_DB_HOST', '127.0.0.1'),
        'port' => env('DBPULL_PRODUCTION_DB_PORT', '3306'),
        'database' => env('DBPULL_PRODUCTION_DB_DATABASE'),
        'username' => env('DBPULL_PRODUCTION_DB_USERNAME'),
        'password' => env('DBPULL_PRODUCTION_DB_PASSWORD'),
        'base_path' => env('DBPULL_PRODUCTION_BASE_PATH'),
        'migrations_path' => env('DBPULL_PRODUCTION_MIGRATIONS_PATH', 'database/migrations'),
        'ssh' => env('DBPULL_PRODUCTION_SSH'),
    ],

    /* copy the block above to add additional remote source */
];
