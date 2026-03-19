<?php

// src/Clients/BaseClient.php

namespace DancingJanissary\SeoIndexing\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseClient
{
    protected array $httpConfig;

    public function __construct(array $httpConfig)
    {
        $this->httpConfig = $httpConfig;
    }

    /*
    |--------------------------------------------------------------------------
    | Build a pre-configured HTTP client instance
    |--------------------------------------------------------------------------
    */
    protected function buildHttpClient(array $headers = []): PendingRequest
    {
        $retry = $this->httpConfig['retry'];

        return Http::withHeaders($headers)
            ->timeout($this->httpConfig['timeout'])
            ->connectTimeout($this->httpConfig['connect_timeout'])
            ->retry(
                times: $retry['times'],
                sleepMilliseconds: $retry['sleep'],
                when: fn ($exception) => $this->shouldRetry($exception),
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Retry only on network/server errors, not on client errors
    |--------------------------------------------------------------------------
    | 4xx errors (bad request, unauthorized) should not be retried —
    | they will fail again with the same result.
    | 5xx and connection errors should be retried.
    */
    protected function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            return $exception->response->status() >= 500;
        }

        // Retry on connection errors, timeouts, etc.
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Parse error message from an HTTP response
    |--------------------------------------------------------------------------
    */
    protected function parseErrorMessage(Response $response): string
    {
        $body = $response->json();

        // Google error format: { "error": { "message": "..." } }
        if (isset($body['error']['message'])) {
            return $body['error']['message'];
        }

        // Generic format
        if (isset($body['message'])) {
            return $body['message'];
        }

        return "HTTP {$response->status()} error";
    }
}
