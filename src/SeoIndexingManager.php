<?php

// src/SeoIndexingManager.php

namespace DancingJanissary\SeoIndexing;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\Jobs\SubmitUrlJob;
use Illuminate\Database\Eloquent\Model;

class SeoIndexingManager
{
    /*
    | Actions — mirrors Google's type names.
    | IndexNow uses the same constants internally even though
    | it doesn't distinguish them in its API payload.
    */
    public const ACTION_UPDATED = 'URL_UPDATED';
    public const ACTION_DELETED = 'URL_DELETED';

    public function __construct(
        protected GoogleIndexingClient $google,
        protected IndexNowClient       $indexNow,
        protected IndexingLogger       $logger,
        protected array                $config,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Submit a single URL as updated
    |--------------------------------------------------------------------------
    */
    public function submit(string $url, ?Model $indexable = null): void
    {
        $this->dispatch($url, self::ACTION_UPDATED, $indexable);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit a single URL as deleted
    |--------------------------------------------------------------------------
    */
    public function delete(string $url, ?Model $indexable = null): void
    {
        $this->dispatch($url, self::ACTION_DELETED, $indexable);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit multiple URLs as updated
    |--------------------------------------------------------------------------
    */
    public function submitBatch(array $urls, ?Model $indexable = null): void
    {
        $this->dispatchBatch($urls, self::ACTION_UPDATED, $indexable);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit multiple URLs as deleted
    |--------------------------------------------------------------------------
    */
    public function deleteBatch(array $urls, ?Model $indexable = null): void
    {
        $this->dispatchBatch($urls, self::ACTION_DELETED, $indexable);
    }

    /*
    |--------------------------------------------------------------------------
    | Core dispatch — single URL
    |--------------------------------------------------------------------------
    | Iterates enabled engines and either dispatches a job
    | or submits synchronously based on config.
    */
    protected function dispatch(
        string  $url,
        string  $action,
        ?Model  $indexable = null,
    ): void {
        foreach ($this->enabledEngines() as $engine) {

            // Skip if already submitted recently (dedup guard)
            if ($this->logger->wasRecentlySubmitted($url, $engine)) {
                continue;
            }

            if ($this->queueEnabled()) {
                $this->dispatchJob($engine, $url, $action, $indexable);
                continue;
            }

            // Sync fallback
            $client = $this->resolveClient($engine);
            $result = $client->submit($url, $action);
            $this->logger->log($result, $indexable, queued: false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Core dispatch — batch URLs
    |--------------------------------------------------------------------------
    */
    protected function dispatchBatch(
        array  $urls,
        string $action,
        ?Model $indexable = null,
    ): void {
        foreach ($this->enabledEngines() as $engine) {

            if ($this->queueEnabled()) {
                $this->dispatchBatchJob($engine, $urls, $action, $indexable);
                continue;
            }

            // Sync fallback
            $client  = $this->resolveClient($engine);
            $results = $client->submitBatch($urls, $action);
            $this->logger->logMany($results, $indexable, queued: false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatch a single-URL job
    |--------------------------------------------------------------------------
    */
    protected function dispatchJob(
        string  $engine,
        string  $url,
        string  $action,
        ?Model  $indexable,
    ): void {
        $job = new SubmitUrlJob(
            engine:        $engine,
            url:           $url,
            action:        $action,
            indexableType: $indexable ? get_class($indexable) : null,
            indexableId:   $indexable?->getKey(),
        );

        $this->configureJob($job)->dispatch();
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatch a batch job
    |--------------------------------------------------------------------------
    */
    protected function dispatchBatchJob(
        string  $engine,
        array   $urls,
        string  $action,
        ?Model  $indexable,
    ): void {
        $job = new SubmitUrlJob(
            engine:        $engine,
            url:           $urls[0],   // reference URL for logging
            action:        $action,
            indexableType: $indexable ? get_class($indexable) : null,
            indexableId:   $indexable?->getKey(),
            isBatch:       true,
            batchUrls:     $urls,
        );

        $this->configureJob($job)->dispatch();
    }

    /*
    |--------------------------------------------------------------------------
    | Apply queue config to a job instance
    |--------------------------------------------------------------------------
    */
    protected function configureJob(SubmitUrlJob $job): SubmitUrlJob
    {
        $queueConfig = $this->config['queue'];

        return $job
            ->onConnection($queueConfig['connection'])
            ->onQueue($queueConfig['name']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    protected function enabledEngines(): array
    {
        return array_keys(
            array_filter($this->config['engines'])
        );
    }

    protected function queueEnabled(): bool
    {
        return (bool) ($this->config['queue']['enabled'] ?? true);
    }

    protected function resolveClient(string $engine): mixed
    {
        return match ($engine) {
            'google'   => $this->google,
            'indexnow' => $this->indexNow,
            default    => throw new \InvalidArgumentException(
                "Unknown engine: [{$engine}]"
            ),
        };
    }
}