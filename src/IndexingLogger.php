<?php

// src/IndexingLogger.php

namespace DancingJanissary\SeoIndexing;

use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;
use Illuminate\Database\Eloquent\Model;

class IndexingLogger
{
    public function __construct(
        protected bool $enabled,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Log a single result
    |--------------------------------------------------------------------------
    */
    public function log(
        IndexingResult $result,
        ?Model         $indexable = null,
        ?string        $jobId     = null,
        bool           $queued    = false,
    ): ?SeoIndexingLog {
        if (! $this->enabled) {
            return null;
        }

        return SeoIndexingLog::fromResult(
            result:    $result,
            indexable: $indexable,
            jobId:     $jobId,
            queued:    $queued,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Log multiple results (batch submissions)
    |--------------------------------------------------------------------------
    */
    public function logMany(
        array   $results,
        ?Model  $indexable = null,
        ?string $jobId     = null,
        bool    $queued    = false,
    ): array {
        if (! $this->enabled) {
            return [];
        }

        return array_map(
            fn (IndexingResult $result) => $this->log($result, $indexable, $jobId, $queued),
            $results,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience: did this URL succeed for a given engine recently?
    |--------------------------------------------------------------------------
    | Useful to skip re-submission if already indexed within N minutes.
    */
    public function wasRecentlySubmitted(
        string $url,
        string $engine,
        int    $withinMinutes = 60,
    ): bool {
        if (! $this->enabled) {
            return false;
        }

        return SeoIndexingLog::query()
            ->forUrl($url)
            ->forEngine($engine)
            ->successful()
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }
}