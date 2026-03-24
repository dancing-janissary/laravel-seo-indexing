<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Clients\BaseClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class BaseClientTest extends TestCase
{
    private function makeClient(): ConcreteTestClient
    {
        return new ConcreteTestClient([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'retry'           => ['times' => 3, 'sleep' => 1000],
        ]);
    }

    public function test_should_retry_returns_true_for_5xx_request_exception(): void
    {
        $client = $this->makeClient();

        $psrResponse = new \GuzzleHttp\Psr7\Response(500, [], 'Server Error');
        $response = new Response($psrResponse);
        $exception = new RequestException($response);

        $this->assertTrue($client->callShouldRetry($exception));
    }

    public function test_should_retry_returns_false_for_4xx_request_exception(): void
    {
        $client = $this->makeClient();

        $psrResponse = new \GuzzleHttp\Psr7\Response(403, [], 'Forbidden');
        $response = new Response($psrResponse);
        $exception = new RequestException($response);

        $this->assertFalse($client->callShouldRetry($exception));
    }

    public function test_should_retry_returns_true_for_non_request_exception(): void
    {
        $client = $this->makeClient();

        $this->assertTrue($client->callShouldRetry(new \RuntimeException('Connection reset')));
    }

    public function test_parse_error_message_extracts_nested_error(): void
    {
        $client = $this->makeClient();

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn(['error' => ['message' => 'Rate limit exceeded']]);
        $response->method('status')->willReturn(429);

        $this->assertSame('Rate limit exceeded', $client->callParseErrorMessage($response));
    }

    public function test_parse_error_message_extracts_top_level_message(): void
    {
        $client = $this->makeClient();

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn(['message' => 'Not found']);
        $response->method('status')->willReturn(404);

        $this->assertSame('Not found', $client->callParseErrorMessage($response));
    }

    public function test_parse_error_message_falls_back_to_http_status(): void
    {
        $client = $this->makeClient();

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn([]);
        $response->method('status')->willReturn(502);

        $this->assertSame('HTTP 502 error', $client->callParseErrorMessage($response));
    }
}

/**
 * Concrete implementation to test abstract BaseClient methods.
 */
class ConcreteTestClient extends BaseClient
{
    public function callShouldRetry(\Throwable $exception): bool
    {
        return $this->shouldRetry($exception);
    }

    public function callParseErrorMessage(Response $response): string
    {
        return $this->parseErrorMessage($response);
    }
}
