<?php

use Illuminate\Support\Str;
use RonNorthrip\DBPull\Commands\DBPull;

it('can figure out the mysql tables key', function () {
    $dbpull = new DBPull();
    $method = new ReflectionMethod(DBPull::class, 'local_tables_key');
    $result = $method->invoke($dbpull);
    $dbname = 'Tables_in_'.Str::snake(config('database.connections.testing.database'));
    expect($result)->toBe($dbname);
});
