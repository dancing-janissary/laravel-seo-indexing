<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\IndexingLogger;
use DancingJanissary\SeoIndexing\Models\SeoIndexingLog;
use DancingJanissary\SeoIndexing\Tests\TestCase;

class IndexingLoggerTest extends TestCase
{
    private function makeResult(bool $success = true, string $url = 'https://example.com'): IndexingResult
    {
        return $success
            ? IndexingResult::success(engine: 'google', url: $url, action: 'URL_UPDATED')
            : IndexingResult::failure(engine: 'google', url: $url, action: 'URL_UPDATED', httpStatus: 500, message: 'fail');
    }

    public function test_log_creates_database_record_when_enabled(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $result = $this->makeResult();

        $log = $logger->log($result, jobId: 'test-job-id', queued: true);

        $this->assertInstanceOf(SeoIndexingLog::class, $log);
        $this->assertDatabaseHas('seo_indexing_logs', [
            'url'    => 'https://example.com',
            'engine' => 'google',
            'action' => 'URL_UPDATED',
            'success' => true,
            'job_id'  => 'test-job-id',
            'queued'  => true,
        ]);
    }

    public function test_log_returns_null_when_disabled(): void
    {
        $logger = new IndexingLogger(enabled: false);

        $log = $logger->log($this->makeResult());

        $this->assertNull($log);
        $this->assertDatabaseCount('seo_indexing_logs', 0);
    }

    public function test_log_many_creates_multiple_records(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $results = [
            $this->makeResult(url: 'https://example.com/a'),
            $this->makeResult(url: 'https://example.com/b'),
            $this->makeResult(success: false, url: 'https://example.com/c'),
        ];

        $logs = $logger->logMany($results, jobId: 'batch-1');

        $this->assertCount(3, $logs);
        $this->assertDatabaseCount('seo_indexing_logs', 3);
    }

    public function test_log_many_returns_empty_when_disabled(): void
    {
        $logger = new IndexingLogger(enabled: false);

        $logs = $logger->logMany([$this->makeResult()]);

        $this->assertSame([], $logs);
        $this->assertDatabaseCount('seo_indexing_logs', 0);
    }

    public function test_was_recently_submitted_returns_true_for_recent_success(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $logger->log($this->makeResult());

        $this->assertTrue($logger->wasRecentlySubmitted('https://example.com', 'google'));
    }

    public function test_was_recently_submitted_returns_false_for_different_url(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $logger->log($this->makeResult());

        $this->assertFalse($logger->wasRecentlySubmitted('https://other.com', 'google'));
    }

    public function test_was_recently_submitted_returns_false_for_different_engine(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $logger->log($this->makeResult());

        $this->assertFalse($logger->wasRecentlySubmitted('https://example.com', 'indexnow'));
    }

    public function test_was_recently_submitted_returns_false_for_failed_submissions(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $logger->log($this->makeResult(success: false));

        $this->assertFalse($logger->wasRecentlySubmitted('https://example.com', 'google'));
    }

    public function test_was_recently_submitted_returns_false_when_disabled(): void
    {
        $logger = new IndexingLogger(enabled: false);

        $this->assertFalse($logger->wasRecentlySubmitted('https://example.com', 'google'));
    }

    public function test_was_recently_submitted_respects_time_window(): void
    {
        $logger = new IndexingLogger(enabled: true);
        $logger->log($this->makeResult());

        // Within default 60 minutes
        $this->assertTrue($logger->wasRecentlySubmitted('https://example.com', 'google', 60));

        // Manually age the record
        SeoIndexingLog::query()->update(['created_at' => now()->subMinutes(120)]);

        $this->assertFalse($logger->wasRecentlySubmitted('https://example.com', 'google', 60));
    }
}
