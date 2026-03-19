<?php

// src/Clients/IndexNowClient.php

namespace DancingJanissary\SeoIndexing\Clients;

use DancingJanissary\SeoIndexing\Contracts\IndexingClientContract;
use DancingJanissary\SeoIndexing\Data\IndexingResult;

class IndexNowClient extends BaseClient implements IndexingClientContract
{
    protected const ENGINE = 'indexnow';

    /*
    | IndexNow uses a single action model — there is no explicit
    | 'deleted' type. Deletion is communicated by submitting the URL
    | with the expectation the engine re-crawls and finds a 404/410.
    | We log the original action but always send the same payload.
    */
    protected const SUPPORTED_ACTIONS = ['URL_UPDATED', 'URL_DELETED'];

    protected array $indexNowConfig;

    public function __construct(array $indexNowConfig, array $httpConfig)
    {
        parent::__construct($httpConfig);
        $this->indexNowConfig = $indexNowConfig;
    }

    /*
    |--------------------------------------------------------------------------
    | Submit a single URL
    |--------------------------------------------------------------------------
    | Pings all configured IndexNow engines (Bing, Yandex, etc.)
    | Returns the first successful result, or the last failure.
    */
    public function submit(string $url, string $action): IndexingResult
    {
        return $this->submitBatch([$url], $action)[0];
    }

    /*
    |--------------------------------------------------------------------------
    | Submit multiple URLs (IndexNow native batch)
    |--------------------------------------------------------------------------
    | IndexNow supports submitting up to 10,000 URLs in a single request.
    | We send the batch to all configured engines and return one
    | IndexingResult per engine, tagged with the first URL for reference.
    */
    public function submitBatch(array $urls, string $action): array
    {
        $results  = [];
        $key      = $this->indexNowConfig['key'];
        $host     = rtrim($this->indexNowConfig['host'], '/');
        $keyFile  = $this->indexNowConfig['key_file'] ?? "{$key}.txt";

        $payload = [
            'host'    => parse_url($host, PHP_URL_HOST) ?? $host,
            'key'     => $key,
            'keyLocation' => "{$host}/{$keyFile}",
            'urlList' => array_values($urls),
        ];

        foreach ($this->indexNowConfig['engines'] as $engineEndpoint) {
            $results[] = $this->pingEngine($engineEndpoint, $urls[0], $action, $payload);
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | Ping a single IndexNow engine endpoint
    |--------------------------------------------------------------------------
    */
    protected function pingEngine(
        string $endpoint,
        string $referenceUrl,
        string $action,
        array  $payload,
    ): IndexingResult {
        try {
            $response = $this->buildHttpClient([
                'Content-Type' => 'application/json; charset=utf-8',
            ])->post($endpoint, $payload);

            /*
            | IndexNow response codes:
            | 200 → OK, key validated
            | 202 → Accepted (key not yet validated but URL accepted)
            | 400 → Invalid format
            | 403 → Key not valid
            | 422 → URLs don't belong to the host
            | 429 → Too many requests
            */
            if ($response->successful() || $response->status() === 202) {
                return IndexingResult::success(
                    engine:     self::ENGINE . ':' . parse_url($endpoint, PHP_URL_HOST),
                    url:        $referenceUrl,
                    action:     $action,
                    httpStatus: $response->status(),
                );
            }

            return IndexingResult::failure(
                engine:     self::ENGINE . ':' . parse_url($endpoint, PHP_URL_HOST),
                url:        $referenceUrl,
                action:     $action,
                httpStatus: $response->status(),
                message:    $this->parseErrorMessage($response),
            );

        } catch (\Throwable $e) {
            return IndexingResult::failure(
                engine:     self::ENGINE . ':' . parse_url($endpoint, PHP_URL_HOST),
                url:        $referenceUrl,
                action:     $action,
                httpStatus: 0,
                message:    $e->getMessage(),
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate configuration
    |--------------------------------------------------------------------------
    */
    public function isConfigured(): bool
    {
        return ! empty($this->indexNowConfig['key'])
            && ! empty($this->indexNowConfig['host']);
    }

    public function getEngine(): string
    {
        return self::ENGINE;
    }
}