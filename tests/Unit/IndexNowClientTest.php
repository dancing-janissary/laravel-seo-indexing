<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\IndexNowClient;
use DancingJanissary\SeoIndexing\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class IndexNowClientTest extends TestCase
{
    private function makeClient(array $configOverrides = []): IndexNowClient
    {
        $config = array_merge([
            'key'     => 'test-api-key-12345',
            'key_file' => null,
            'host'    => 'https://example.com',
            'engines' => ['https://api.indexnow.org/indexnow'],
        ], $configOverrides);

        return new IndexNowClient($config, [
            'timeout'         => 10,
            'connect_timeout' => 5,
            'retry'           => ['times' => 1, 'sleep' => 100],
        ]);
    }

    // ── isConfigured ────────────────────────────────────────────────

    public function test_is_configured_returns_true_with_key_and_host(): void
    {
        $client = $this->makeClient();
        $this->assertTrue($client->isConfigured());
    }

    public function test_is_configured_returns_false_without_key(): void
    {
        $client = $this->makeClient(['key' => '']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_false_without_host(): void
    {
        $client = $this->makeClient(['host' => '']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_get_engine_returns_indexnow(): void
    {
        $this->assertSame('indexnow', $this->makeClient()->getEngine());
    }

    // ── submit / submitBatch ────────────────────────────────────────

    public function test_submit_single_url_success(): void
    {
        Http::fake(['https://api.indexnow.org/*' => Http::response('', 200)]);

        $client = $this->makeClient();
        $result = $client->submit('https://example.com/page', 'URL_UPDATED');

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatus);
        $this->assertStringContainsString('indexnow', $result->engine);
    }

    public function test_submit_accepts_202_as_success(): void
    {
        Http::fake(['https://api.indexnow.org/*' => Http::response('', 202)]);

        $client = $this->makeClient();
        $result = $client->submit('https://example.com/page', 'URL_UPDATED');

        $this->assertTrue($result->success);
        $this->assertSame(202, $result->httpStatus);
    }

    public function test_submit_handles_403_failure(): void
    {
        Http::fake(['https://api.indexnow.org/*' => Http::response(['message' => 'Key not valid'], 403)]);

        $client = $this->makeClient();
        $result = $client->submit('https://example.com/page', 'URL_UPDATED');

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->httpStatus);
        $this->assertSame('Key not valid', $result->message);
    }

    public function test_submit_handles_422_failure(): void
    {
        Http::fake(['https://api.indexnow.org/*' => Http::response(['message' => 'URLs do not belong to host'], 422)]);

        $client = $this->makeClient();
        $result = $client->submit('https://other.com/page', 'URL_UPDATED');

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpStatus);
    }

    public function test_submit_batch_pings_all_configured_engines(): void
    {
        Http::fake([
            'https://api.indexnow.org/*' => Http::response('', 200),
            'https://www.bing.com/*'     => Http::response('', 200),
        ]);

        $client = $this->makeClient([
            'engines' => [
                'https://api.indexnow.org/indexnow',
                'https://www.bing.com/indexnow',
            ],
        ]);

        $results = $client->submitBatch(['https://example.com/a', 'https://example.com/b'], 'URL_UPDATED');

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->success);
        $this->assertTrue($results[1]->success);
    }

    public function test_submit_batch_sends_correct_payload(): void
    {
        Http::fake(['https://api.indexnow.org/*' => Http::response('', 200)]);

        $client = $this->makeClient();
        $client->submitBatch(['https://example.com/a', 'https://example.com/b'], 'URL_UPDATED');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['host'] === 'example.com'
                && $body['key'] === 'test-api-key-12345'
                && $body['urlList'] === ['https://example.com/a', 'https://example.com/b']
                && str_contains($body['keyLocation'], 'test-api-key-12345.txt');
        });
    }

    public function test_submit_uses_custom_key_file(): void
    {
        Http::fake(['https://api.indexnow.org/*' => Http::response('', 200)]);

        $client = $this->makeClient(['key_file' => 'my-custom-key.txt']);
        $client->submit('https://example.com/page', 'URL_UPDATED');

        Http::assertSent(function ($request) {
            return str_contains($request->data()['keyLocation'], 'my-custom-key.txt');
        });
    }

    public function test_submit_handles_network_exception(): void
    {
        Http::fake(['https://api.indexnow.org/*' => fn () => throw new \Exception('Connection refused')]);

        $client = $this->makeClient();
        $result = $client->submit('https://example.com/page', 'URL_UPDATED');

        $this->assertFalse($result->success);
        $this->assertSame(0, $result->httpStatus);
        $this->assertStringContainsString('Connection refused', $result->message);
    }
}
