<?php

namespace Athwari\ZktecoAdms;

use Athwari\ZktecoAdms\Console\EvictStaleDevicesCommand;
use Athwari\ZktecoAdms\Http\Middleware\ValidateDeviceRequest;
use Athwari\ZktecoAdms\Services\AttendanceParser;
use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Illuminate\Support\ServiceProvider;

class ZktecoAdmsServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/zkteco-adms.php', 'zkteco-adms');

        // Register singletons
        $this->app->singleton(AttendanceParser::class, function () {
            return new AttendanceParser();
        });

        $this->app->singleton(DeviceManager::class, function ($app) {
            return new DeviceManager($app->make(AttendanceParser::class));
        });

        $this->app->singleton(CommandManager::class, function () {
            return new CommandManager();
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/zkteco-adms.php' => config_path('zkteco-adms.php'),
        ], 'zkteco-adms-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'zkteco-adms-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/adms.php');

        // Register middleware alias
        $router = $this->app->make('router');
        $router->aliasMiddleware('zkteco.validate', ValidateDeviceRequest::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                EvictStaleDevicesCommand::class,
            ]);
        }
    }
}
