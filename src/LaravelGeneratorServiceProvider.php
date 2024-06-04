<?php

namespace Fase\LaravelGenerator;

use Fase\LaravelGenerator\Commands\GenerateTest;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Fase\LaravelGenerator\Commands\GenerateTestCase;

class LaravelGeneratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravelgenerator')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravelgenerator_table')
            ->hasCommands([GenerateTestCase::class, GenerateTest::class]);
    }

    public function boot()
    {
        // Register the command if we are using the application via the CLI
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTestCase::class,
                GenerateTest::class
            ]);
        }
    }
}
