<?php

namespace JulianPitt\DBManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class LaravelMySqlBackupServiceProvider extends ServiceProvider
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
        // use this if your package has views
        $this->loadViewsFrom(realpath(__DIR__.'/resources/views'), 'LaravelMySqlBackup');

        // use this if your package has routes
        //$this->setupRoutes($this->app->router);


         $this->publishes([
                 __DIR__.'/config/config.php' => config_path('LaravelMySqlBackup.php'),
         ]);


         $this->mergeConfigFrom(
             __DIR__.'/config/config.php', 'LaravelMySqlBackup'
         );
    }
    /**
     * Define the routes for the application.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function setupRoutes(Router $router)
    {
//        $router->group(['namespace' => 'julianpitt\LaravelMySqlBackup\Http\Controllers'], function($router)
//        {
//            require __DIR__.'/Http/routes.php';
//        });
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        //$this->registerLaravelMySqlBackup();
        $this->app['command.dbmysql:backup'] = $this->app->share(
            function ($app) {
                return new Commands\BackupCommands();
            }
        );

        $this->app['command.dbmysql:restore'] = $this->app->share(
            function ($app) {
                return new Commands\RestoreCommands();
            }
        );

        $this->commands(['command.dbmysql:backup', 'command.dbmysql:restore']);

         config([
                 'config/LaravelMySqlBackup.php',
         ]);
    }
    private function registerLaravelMySqlBackup()
    {
        $this->app->bind('LaravelMySQLBackup',function($app){
            return new LaravelMySQLBackup($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [
            'command.dbmysql:backup',
            'command.dbmysql:restore',
        ];
    }

}