<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\GoogleIndexingClient;
use PHPUnit\Framework\TestCase;

class GoogleIndexingClientTest extends TestCase
{
    private function makeClient(array $googleConfig = [], array $httpConfig = []): GoogleIndexingClient
    {
        return new GoogleIndexingClient(
            array_merge([
                'credentials_path' => '/nonexistent/path.json',
                'scopes'           => ['https://www.googleapis.com/auth/indexing'],
            ], $googleConfig),
            array_merge([
                'timeout'         => 30,
                'connect_timeout' => 10,
                'retry'           => ['times' => 3, 'sleep' => 1000],
            ], $httpConfig),
        );
    }

    public function test_get_engine_returns_google(): void
    {
        $this->assertSame('google', $this->makeClient()->getEngine());
    }

    public function test_is_configured_returns_false_when_credentials_path_missing(): void
    {
        $client = $this->makeClient(['credentials_path' => '']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_credentials_path_null(): void
    {
        $client = $this->makeClient(['credentials_path' => null]);
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_file_does_not_exist(): void
    {
        $client = $this->makeClient(['credentials_path' => '/tmp/nonexistent-credentials-file.json']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_true_when_credentials_file_exists(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'google_creds_');
        file_put_contents($tmpFile, '{}');

        try {
            $client = $this->makeClient(['credentials_path' => $tmpFile]);
            $this->assertTrue($client->isConfigured());
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_submit_returns_failure_when_credentials_invalid(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'google_creds_');
        file_put_contents($tmpFile, '{"type":"invalid"}');

        try {
            $client = $this->makeClient(['credentials_path' => $tmpFile]);
            $result = $client->submit('https://example.com/page', 'URL_UPDATED');

            $this->assertFalse($result->success);
            $this->assertSame('google', $result->engine);
            $this->assertSame('https://example.com/page', $result->url);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_submit_batch_returns_failure_for_all_urls_on_auth_error(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'google_creds_');
        file_put_contents($tmpFile, '{"type":"invalid"}');

        try {
            $client = $this->makeClient(['credentials_path' => $tmpFile]);
            $urls = ['https://example.com/a', 'https://example.com/b'];
            $results = $client->submitBatch($urls, 'URL_UPDATED');

            $this->assertCount(2, $results);
            $this->assertFalse($results[0]->success);
            $this->assertFalse($results[1]->success);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_batch_request_key_is_never_falsy_for_index_zero(): void
    {
        $client = $this->makeClient();

        $key = $this->invokeProtected($client, 'batchRequestKey', [0]);

        $this->assertSame('req-0', $key);
        $this->assertFalse(false == $key, 'Batch key must survive Google\\Http\\Batch::add falsy check');
        $this->assertSame('req-1', $this->invokeProtected($client, 'batchRequestKey', [1]));
    }

    public function test_missing_batch_response_message_describes_empty_body(): void
    {
        $client = $this->makeClient();

        $message = $this->invokeProtected($client, 'buildMissingBatchResponseMessage', [
            1,
            'response-req-1',
            ['https://example.com/a', 'https://example.com/b'],
            null,
        ]);

        $this->assertStringContainsString('batch position 2/2', $message);
        $this->assertStringContainsString('expected key "response-req-1"', $message);
        $this->assertStringContainsString('empty body', $message);
    }

    public function test_missing_batch_response_message_describes_partial_batch(): void
    {
        $client = $this->makeClient();

        $message = $this->invokeProtected($client, 'buildMissingBatchResponseMessage', [
            1,
            'response-req-1',
            ['https://example.com/a', 'https://example.com/b', 'https://example.com/c'],
            ['response-req-0' => new \stdClass()],
        ]);

        $this->assertStringContainsString('only 1 of 3 batch sub-responses', $message);
        $this->assertStringContainsString('response-req-0', $message);
        $this->assertStringContainsString('omitted from the multipart batch response', $message);
    }

    public function test_missing_batch_response_message_describes_key_mismatch(): void
    {
        $client = $this->makeClient();

        $message = $this->invokeProtected($client, 'buildMissingBatchResponseMessage', [
            1,
            'response-req-1',
            ['https://example.com/a', 'https://example.com/b'],
            [
                'response-req-0' => new \stdClass(),
                'response-req-2' => new \stdClass(),
            ],
        ]);

        $this->assertStringContainsString('Content-ID / request-key mismatch', $message);
        $this->assertStringContainsString('response-req-0, response-req-2', $message);
    }

    private function invokeProtected(object $object, string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }
}
