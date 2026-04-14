<?php

declare(strict_types=1);

namespace HamedElasma\EntraSync\Tests;

use HamedElasma\EntraSync\EntraSyncServiceProvider;
use HamedElasma\EntraSync\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [EntraSyncServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('entra-sync.tenant_id', 'test-tenant-id');
        $app['config']->set('entra-sync.client_id', 'test-client-id');
        $app['config']->set('entra-sync.client_secret', 'test-client-secret');
        $app['config']->set('entra-sync.user_model', User::class);
    }
}
