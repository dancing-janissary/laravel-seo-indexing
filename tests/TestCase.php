<?php

namespace DancingJanissary\SeoIndexing\Tests;

use DancingJanissary\SeoIndexing\SeoIndexingServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SeoIndexingServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'SeoIndexing' => \DancingJanissary\SeoIndexing\Facades\SeoIndexing::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('seo-indexing.engines', [
            'google'   => true,
            'indexnow' => true,
        ]);

        $app['config']->set('seo-indexing.queue.enabled', false);
        $app['config']->set('seo-indexing.logging.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
