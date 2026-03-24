<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\IndexingLogger;
use DancingJanissary\SeoIndexing\SeoIndexingManager;
use DancingJanissary\SeoIndexing\Tests\TestCase;

class SeoIndexingServiceProviderTest extends TestCase
{
    public function test_manager_is_registered_in_container(): void
    {
        $this->assertInstanceOf(
            SeoIndexingManager::class,
            $this->app->make(SeoIndexingManager::class)
        );
    }

    public function test_manager_is_bound_as_singleton(): void
    {
        $a = $this->app->make(SeoIndexingManager::class);
        $b = $this->app->make(SeoIndexingManager::class);

        $this->assertSame($a, $b);
    }

    public function test_facade_alias_is_registered(): void
    {
        $this->assertInstanceOf(
            SeoIndexingManager::class,
            $this->app->make('seo-indexing')
        );
    }

    public function test_google_client_is_registered(): void
    {
        $this->assertInstanceOf(
            GoogleIndexingClient::class,
            $this->app->make(GoogleIndexingClient::class)
        );
    }

    public function test_indexnow_client_is_registered(): void
    {
        $this->assertInstanceOf(
            IndexNowClient::class,
            $this->app->make(IndexNowClient::class)
        );
    }

    public function test_logger_is_registered(): void
    {
        $this->assertInstanceOf(
            IndexingLogger::class,
            $this->app->make(IndexingLogger::class)
        );
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('seo-indexing'));
        $this->assertIsArray(config('seo-indexing.engines'));
        $this->assertIsArray(config('seo-indexing.queue'));
    }
}
