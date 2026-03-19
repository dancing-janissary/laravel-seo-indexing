<?php

// src/Jobs/SubmitUrlJob.php

namespace DancingJanissary\SeoIndexing\Jobs;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\Contracts\IndexingClientContract;
use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\IndexingLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SubmitUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    | How many times Laravel should retry this job before marking it failed.
    | The HTTP client also retries internally (config: http.retry.times),
    | so total attempts = $tries × http.retry.times in the worst case.
    | We keep job-level retries low to avoid burning API quotas.
    */
    public int $tries = 2;

    /*
    | Seconds before the job is considered timed out.
    | Should be higher than http.timeout + http.connect_timeout.
    */
    public int $timeout = 60;

    /*
    | Unique job ID for log traceability.
    */
    public string $jobId;

    public function __construct(
        public readonly string  $engine,       // 'google' | 'indexnow'
        public readonly string  $url,
        public readonly string  $action,
        public readonly ?string $indexableType = null,
        public readonly mixed   $indexableId   = null,
        public readonly bool    $isBatch       = false,
        public readonly array   $batchUrls     = [],
    ) {
        $this->jobId = (string) Str::uuid();
    }

    /*
    |--------------------------------------------------------------------------
    | Handle
    |--------------------------------------------------------------------------
    */
    public function handle(IndexingLogger $logger): void
    {
        $client    = $this->resolveClient();
        $indexable = $this->resolveIndexable();

        if (! $client->isConfigured()) {
            $this->fail(
                new \RuntimeException(
                    "Engine [{$this->engine}] is not properly configured. " .
                    "Check your seo-indexing config and credentials."
                )
            );
            return;
        }

        if ($this->isBatch) {
            $results = $client->submitBatch($this->batchUrls, $this->action);
            $logger->logMany($results, $indexable, $this->jobId, queued: true);
            return;
        }

        $result = $client->submit($this->url, $this->action);
        $logger->log($result, $indexable, $this->jobId, queued: true);

        /*
        | If the submission failed and we have retries left, re-throw
        | so Laravel queues the retry automatically.
        */
        if (! $result->success && $this->attempts() < $this->tries) {
            throw new \RuntimeException(
                "Indexing submission failed for [{$this->engine}]: {$result->message}"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Failed — called when all retries are exhausted
    |--------------------------------------------------------------------------
    */
    public function failed(\Throwable $exception): void
    {
        $logger = app(IndexingLogger::class);

        $result = IndexingResult::failure(
            engine:     $this->engine,
            url:        $this->url,
            action:     $this->action,
            httpStatus: 0,
            message:    "Job failed after {$this->tries} attempts: " . $exception->getMessage(),
        );

        $logger->log(
            result:    $result,
            indexable: $this->resolveIndexable(),
            jobId:     $this->jobId,
            queued:    true,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve the correct client from the container
    |--------------------------------------------------------------------------
    */
    protected function resolveClient(): IndexingClientContract
    {
        return match ($this->engine) {
            'google'   => app(GoogleIndexingClient::class),
            'indexnow' => app(IndexNowClient::class),
            default    => throw new \InvalidArgumentException(
                "Unknown indexing engine: [{$this->engine}]"
            ),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Rebuild the Eloquent model from type + id (stored in job payload)
    |--------------------------------------------------------------------------
    | We store type+id instead of the model itself to avoid serializing
    | large model objects into the queue payload.
    */
    protected function resolveIndexable(): ?Model
    {
        if (! $this->indexableType || ! $this->indexableId) {
            return null;
        }

        return ($this->indexableType)::find($this->indexableId);
    }
}