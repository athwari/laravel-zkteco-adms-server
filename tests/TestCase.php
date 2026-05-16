<?php

namespace Athwari\ZktecoAdms\Tests;

use Athwari\ZktecoAdms\Facades\ZktecoAdms;
use Athwari\ZktecoAdms\ZktecoAdmsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ZktecoAdmsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ZktecoAdms' => ZktecoAdms::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('zkteco-adms.max_devices', 100);
        $app['config']->set('zkteco-adms.max_commands_per_device', 50);
        $app['config']->set('zkteco-adms.online_threshold', 120);
        $app['config']->set('zkteco-adms.default_timezone', 'UTC');
        $app['config']->set('zkteco-adms.enable_inspect', false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
