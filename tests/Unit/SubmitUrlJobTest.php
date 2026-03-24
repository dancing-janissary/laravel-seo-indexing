<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\Data\IndexingResult;
use DancingJanissary\SeoIndexing\IndexingLogger;
use DancingJanissary\SeoIndexing\Jobs\SubmitUrlJob;
use DancingJanissary\SeoIndexing\Tests\TestCase;

class SubmitUrlJobTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $job = new SubmitUrlJob(
            engine: 'google',
            url: 'https://example.com/page',
            action: 'URL_UPDATED',
            indexableType: 'App\\Models\\Post',
            indexableId: 42,
        );

        $this->assertSame('google', $job->engine);
        $this->assertSame('https://example.com/page', $job->url);
        $this->assertSame('URL_UPDATED', $job->action);
        $this->assertSame('App\\Models\\Post', $job->indexableType);
        $this->assertSame(42, $job->indexableId);
        $this->assertFalse($job->isBatch);
        $this->assertSame([], $job->batchUrls);
        $this->assertNotEmpty($job->jobId);
    }

    public function test_job_generates_unique_uuid(): void
    {
        $job1 = new SubmitUrlJob(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED');
        $job2 = new SubmitUrlJob(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED');

        $this->assertNotSame($job1->jobId, $job2->jobId);
    }

    public function test_job_has_correct_retry_and_timeout(): void
    {
        $job = new SubmitUrlJob(engine: 'google', url: 'https://a.com', action: 'URL_UPDATED');

        $this->assertSame(2, $job->tries);
        $this->assertSame(60, $job->timeout);
    }

    public function test_handle_submits_single_url(): void
    {
        $client = $this->createMock(GoogleIndexingClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects($this->once())
            ->method('submit')
            ->with('https://example.com/page', 'URL_UPDATED')
            ->willReturn(IndexingResult::success(engine: 'google', url: 'https://example.com/page', action: 'URL_UPDATED'));

        $this->app->instance(GoogleIndexingClient::class, $client);

        $logger = $this->createMock(IndexingLogger::class);
        $logger->expects($this->once())->method('log');

        $job = new SubmitUrlJob(engine: 'google', url: 'https://example.com/page', action: 'URL_UPDATED');
        $job->handle($logger);
    }

    public function test_handle_submits_batch(): void
    {
        $urls = ['https://example.com/a', 'https://example.com/b'];

        $client = $this->createMock(GoogleIndexingClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects($this->once())
            ->method('submitBatch')
            ->with($urls, 'URL_UPDATED')
            ->willReturn([
                IndexingResult::success(engine: 'google', url: $urls[0], action: 'URL_UPDATED'),
                IndexingResult::success(engine: 'google', url: $urls[1], action: 'URL_UPDATED'),
            ]);

        $this->app->instance(GoogleIndexingClient::class, $client);

        $logger = $this->createMock(IndexingLogger::class);
        $logger->expects($this->once())->method('logMany');

        $job = new SubmitUrlJob(
            engine: 'google',
            url: $urls[0],
            action: 'URL_UPDATED',
            isBatch: true,
            batchUrls: $urls,
        );
        $job->handle($logger);
    }

    public function test_handle_fails_when_engine_not_configured(): void
    {
        $client = $this->createMock(GoogleIndexingClient::class);
        $client->method('isConfigured')->willReturn(false);
        $client->expects($this->never())->method('submit');

        $this->app->instance(GoogleIndexingClient::class, $client);

        $logger = $this->createMock(IndexingLogger::class);
        $logger->expects($this->never())->method('log');

        $job = new SubmitUrlJob(engine: 'google', url: 'https://example.com', action: 'URL_UPDATED');

        // fail() marks the job as failed internally and returns early
        // without calling submit or log — we verify via the mock expectations
        $job->handle($logger);
    }

    public function test_handle_throws_invalid_argument_for_unknown_engine(): void
    {
        $logger = $this->createMock(IndexingLogger::class);

        $job = new SubmitUrlJob(engine: 'yahoo', url: 'https://example.com', action: 'URL_UPDATED');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown indexing engine');

        $job->handle($logger);
    }

    public function test_handle_with_indexnow_engine(): void
    {
        $client = $this->createMock(IndexNowClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects($this->once())
            ->method('submit')
            ->willReturn(IndexingResult::success(engine: 'indexnow', url: 'https://example.com', action: 'URL_UPDATED'));

        $this->app->instance(IndexNowClient::class, $client);

        $logger = $this->createMock(IndexingLogger::class);
        $logger->expects($this->once())->method('log');

        $job = new SubmitUrlJob(engine: 'indexnow', url: 'https://example.com', action: 'URL_UPDATED');
        $job->handle($logger);
    }

    public function test_failed_logs_failure_result(): void
    {
        $logger = $this->createMock(IndexingLogger::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->callback(fn (IndexingResult $r) => $r->success === false && str_contains($r->message, 'Job failed')),
                $this->anything(),
                $this->anything(),
                true,
            );

        $this->app->instance(IndexingLogger::class, $logger);

        $job = new SubmitUrlJob(engine: 'google', url: 'https://example.com', action: 'URL_UPDATED');
        $job->failed(new \RuntimeException('Connection timeout'));
    }
}
