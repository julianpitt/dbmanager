<?php

namespace JulianPitt\DBManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class DBManagerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
         $this->publishes([
                 __DIR__.'/config/db-manager.php' => config_path('db-manager.php'),
         ]);


         $this->mergeConfigFrom(
             __DIR__.'/config/db-manager.php', 'db-manager'
         );
    }


    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {

        $this->app['command.dbman:backup'] = $this->app->share(
            function ($app) {
                return new Commands\BackupCommands();
            }
        );

        $this->app['command.dbman:restore'] = $this->app->share(
            function ($app) {
                return new Commands\RestoreCommands();
            }
        );

        $this->commands(['command.dbman:backup', 'command.dbman:restore']);

         //config('config/db-manager.php');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [
            'command.dbman:backup',
            'command.dbman:restore',
        ];
    }

}