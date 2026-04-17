<?php

// src/SeoIndexingServiceProvider.php

namespace DancingJanissary\SeoIndexing;

use Illuminate\Support\ServiceProvider;
use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\Contracts\IndexingClientContract;

class SeoIndexingServiceProvider extends ServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    | Called after all providers are registered. Safe to use other services.
    */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    | Bind everything into the service container.
    */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/seo-indexing.php',
            'seo-indexing'
        );

        $this->registerClients();

        $this->app->singleton(IndexingLogger::class, function () {
            return new IndexingLogger(
                enabled: config('seo-indexing.logging.enabled', true),
            );
        });
        $this->registerManager();
    }

    /*
    |--------------------------------------------------------------------------
    | Publishables
    |--------------------------------------------------------------------------
    | Assets the consuming Laravel app can publish with artisan.
    */
    protected function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__ . '/../config/seo-indexing.php'
                => config_path('seo-indexing.php'),
        ], 'seo-indexing-config');

        // Migrations
        $this->publishes([
            __DIR__ . '/../database/migrations'
                => database_path('migrations'),
        ], 'seo-indexing-migrations');

        // AI agent skills (Claude Code / compatible agents)
        $this->publishes([
            __DIR__ . '/../resources/skills/seo-indexing'
                => base_path('.claude/skills/seo-indexing'),
        ], 'seo-indexing-skills');

        // Publish all at once with the main tag
        $this->publishes([
            __DIR__ . '/../config/seo-indexing.php'
                => config_path('seo-indexing.php'),
            __DIR__ . '/../database/migrations'
                => database_path('migrations'),
            __DIR__ . '/../resources/skills/seo-indexing'
                => base_path('.claude/skills/seo-indexing'),
        ], 'seo-indexing');
    }

    /*
    |--------------------------------------------------------------------------
    | Clients
    |--------------------------------------------------------------------------
    | Each API client is bound individually so they can be swapped or mocked
    | in tests independently.
    */
    protected function registerClients(): void
    {
        $this->app->singleton(GoogleIndexingClient::class, function ($app) {
            return new GoogleIndexingClient(
                config('seo-indexing.google'),
                config('seo-indexing.http'),
            );
        });

        $this->app->singleton(IndexNowClient::class, function ($app) {
            return new IndexNowClient(
                config('seo-indexing.indexnow'),
                config('seo-indexing.http'),
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Manager
    |--------------------------------------------------------------------------
    | The SeoIndexingManager is the main orchestrator. It's also bound under
    | the 'seo-indexing' key so the Facade can resolve it.
    */
    protected function registerManager(): void
    {
        $this->app->singleton(SeoIndexingManager::class, function ($app) {
            return new SeoIndexingManager(
                google:  $app->make(GoogleIndexingClient::class),
                indexNow: $app->make(IndexNowClient::class),
                logger:  $app->make(IndexingLogger::class),    // ← add this
                config:  config('seo-indexing'),
            );
        });
    
        $this->app->alias(SeoIndexingManager::class, 'seo-indexing');
    }

    /*
    |--------------------------------------------------------------------------
    | Provides
    |--------------------------------------------------------------------------
    | Tells Laravel which bindings this provider offers. Used for deferred
    | loading — not required but good practice to declare.
    */
    public function provides(): array
    {
        return [
            SeoIndexingManager::class,
            GoogleIndexingClient::class,
            IndexNowClient::class,
            IndexingLogger::class,
            'seo-indexing',
        ];
    }
}