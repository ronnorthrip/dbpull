<?php

namespace RonNorthrip\DBPull\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use RonNorthrip\DBPull\DBPullServiceProvider;

//note: compare to https://github.com/spatie/laravel-backup/blob/main/tests/TestCase.php

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'RonNorthrip\\DBPull\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            DBPullServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.connections.testing', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'dbpull_test',
            'username' => 'root',
            'password' => 'password',
        ]);

        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_dbpull_table.php.stub';
        $migration->up();
        */
    }
}
