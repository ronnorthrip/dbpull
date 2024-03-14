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
        $connection = [
            'driver' => env('DB_CONNECTION', 'mysql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'dbpull_test'),
            'username' => env('DB_USERNAME', 'root'),
        ];
        if (env('DB_PASSWORD')) {
            $connection['password'] = env('DB_PASSWORD');
        }

        config()->set('database.connections.testing', $connection);
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_dbpull_table.php.stub';
        $migration->up();
        */
    }
}
