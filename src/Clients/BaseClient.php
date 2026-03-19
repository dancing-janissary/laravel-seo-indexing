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
    | Build a pre-configured Laravel HTTP client instance
    |--------------------------------------------------------------------------
    | Used by IndexNowClient. GoogleIndexingClient uses its own
    | Guzzle instance managed by google/apiclient.
    */
    protected function buildHttpClient(array $headers = []): PendingRequest
    {
        $retry = $this->httpConfig['retry'];

        return Http::withHeaders($headers)
            ->timeout($this->httpConfig['timeout'])
            ->connectTimeout($this->httpConfig['connect_timeout'])
            ->retry(
                times:             $retry['times'],
                sleepMilliseconds: $retry['sleep'],
                when:              fn ($exception) => $this->shouldRetry($exception),
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Retry on 5xx and connection errors only — never on 4xx
    |--------------------------------------------------------------------------
    */
    protected function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            return $exception->response->status() >= 500;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Parse a generic HTTP error response
    |--------------------------------------------------------------------------
    */
    protected function parseErrorMessage(Response $response): string
    {
        $body = $response->json();

        if (isset($body['error']['message'])) {
            return $body['error']['message'];
        }

        if (isset($body['message'])) {
            return $body['message'];
        }

        return "HTTP {$response->status()} error";
    }
}