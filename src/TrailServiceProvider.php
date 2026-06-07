<?php

namespace Trail\Trail;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Trail\Trail\Commands\TrailCommand;

class TrailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-trail')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_trail_table')
            ->hasCommand(TrailCommand::class);
    }
}
