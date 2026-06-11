<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Tests;

use Hwkdo\IntranetAppTickets\IntranetAppTicketsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Hwkdo\\IntranetAppTickets\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            IntranetAppTicketsServiceProvider::class,
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
        $app['config']->set('intranet-app-tickets.webhook.secret', 'test-webhook-secret');
        $app['config']->set('intranet-app-tickets.zammad.url', 'https://zammad.test');
        $app['config']->set('intranet-app-tickets.zammad.http_token', 'test-token');
    }
}
