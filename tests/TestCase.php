<?php

namespace Krato\Verifactu\Tests;

use Krato\Verifactu\VerifactuServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            VerifactuServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('verifactu.environment', 'testing');
        config()->set('verifactu.sif', [
            'name' => 'Test Software',
            'nif' => 'B00000000',
            'version' => '1.0.0',
            'id' => 'TEST-001',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
