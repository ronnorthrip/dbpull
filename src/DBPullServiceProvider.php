<?php

namespace RonNorthrip\DBPull;

use RonNorthrip\DBPull\Commands\DBPullCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DBPullServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('dbpull')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_dbpull_table')
            ->hasCommand(DBPullCommand::class);
    }
}
