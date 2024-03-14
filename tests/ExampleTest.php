<?php

use Illuminate\Support\Str;
use RonNorthrip\DBPull\Commands\DBPull;

it('can figure out the mysql tables key for empty db', function () {
    // $dbname = 'Tables_in_'.Str::snake(config('database.connections.testing.database'));
    $dbpull = new DBPull();
    $method = new ReflectionMethod(DBPull::class, 'local_tables_key');
    $result = $method->invoke($dbpull);
    expect($result)->toBe(null);
});

it('can figure out the mysql tables list for empty db', function () {
    $dbpull = new DBPull();
    $method = new ReflectionMethod(DBPull::class, 'local_get_tables');
    $result = $method->invoke($dbpull);
    expect($result)->toBe([]);
});
