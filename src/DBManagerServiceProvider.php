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
        // use this if your package has views
        $this->loadViewsFrom(realpath(__DIR__.'/resources/views'), 'db-manager');

        // use this if your package has routes
        //$this->setupRoutes($this->app->router);


         $this->publishes([
                 __DIR__.'/config/db-manager.php' => config_path('db-manager.php'),
         ]);


         $this->mergeConfigFrom(
             __DIR__.'/config/db-manager.php', 'db-manager'
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

         config([
                 'config/db-manager.php',
         ]);
    }
    private function registerDBManager()
    {
        $this->app->bind('DBManager',function($app){
            return new DBManager($app);
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
            'command.dbman:backup',
            'command.dbman:restore',
        ];
    }

}