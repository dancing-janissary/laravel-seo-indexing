<?php

// src/Clients/GoogleIndexingClient.php

namespace DancingJanissary\SeoIndexing\Clients;

use DancingJanissary\SeoIndexing\Contracts\IndexingClientContract;
use DancingJanissary\SeoIndexing\Data\IndexingResult;
use Google\Client as GoogleClient;
use Google\Service\Indexing;
use Google\Service\Indexing\UrlNotification;

class GoogleIndexingClient extends BaseClient implements IndexingClientContract
{
    protected const ENGINE         = 'google';
    protected const ACTION_UPDATED = 'URL_UPDATED';
    protected const ACTION_DELETED = 'URL_DELETED';

    protected array  $googleConfig;
    protected ?Indexing $service = null;

    public function __construct(array $googleConfig, array $httpConfig)
    {
        parent::__construct($httpConfig);
        $this->googleConfig = $googleConfig;
    }

    /*
    |--------------------------------------------------------------------------
    | Submit a single URL
    |--------------------------------------------------------------------------
    */
    public function submit(string $url, string $action): IndexingResult
    {
        try {
            $notification = new UrlNotification();
            $notification->setUrl($url);
            $notification->setType($action);

            $response = $this->getService()
                ->urlNotifications
                ->publish($notification);

            return IndexingResult::success(
                engine:     self::ENGINE,
                url:        $url,
                action:     $action,
                httpStatus: 200,
                payload:    $this->responseToArray($response),
            );

        } catch (\Google\Service\Exception $e) {
            return IndexingResult::failure(
                engine:     self::ENGINE,
                url:        $url,
                action:     $action,
                httpStatus: $e->getCode(),
                message:    $this->parseGoogleException($e),
                payload:    $this->parseGoogleExceptionErrors($e),
            );

        } catch (\Throwable $e) {
            return IndexingResult::failure(
                engine:     self::ENGINE,
                url:        $url,
                action:     $action,
                httpStatus: 0,
                message:    $e->getMessage(),
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Submit multiple URLs
    |--------------------------------------------------------------------------
    | Google Indexing API does not support a native batch endpoint for
    | urlNotifications:publish. We use Google's HTTP batch request feature
    | which multiplexes multiple API calls into a single HTTP request,
    | reducing overhead compared to separate requests.
    |
    | Quota: 200 requests/day per service account. Each URL in a batch
    | still counts as one request against this quota.
    */
    public function submitBatch(array $urls, string $action): array
    {
        try {
            $client  = $this->buildGoogleClient();
            $service = new Indexing($client);
            $results = [];

            /*
            | Google's PHP client supports deferred/batched requests.
            | We defer all requests, execute the batch in one HTTP call,
            | then map each response back to an IndexingResult.
            */
            $client->setUseBatch(true);
            $batch = $service->createBatch();

            foreach ($urls as $index => $url) {
                $notification = new UrlNotification();
                $notification->setUrl($url);
                $notification->setType($action);

                $batch->add(
                    $service->urlNotifications->publish($notification),
                    (string) $index,
                );
            }

            $batchResponses = $batch->execute();
            $client->setUseBatch(false);

            foreach ($urls as $index => $url) {
                $responseKey = 'response-' . $index;

                if (! isset($batchResponses[$responseKey])) {
                    $results[] = IndexingResult::failure(
                        engine:     self::ENGINE,
                        url:        $url,
                        action:     $action,
                        httpStatus: 0,
                        message:    'No response received in batch',
                    );
                    continue;
                }

                $response = $batchResponses[$responseKey];

                if ($response instanceof \Google\Service\Exception) {
                    $results[] = IndexingResult::failure(
                        engine:     self::ENGINE,
                        url:        $url,
                        action:     $action,
                        httpStatus: $response->getCode(),
                        message:    $this->parseGoogleException($response),
                        payload:    $this->parseGoogleExceptionErrors($response),
                    );
                    continue;
                }

                $results[] = IndexingResult::success(
                    engine:     self::ENGINE,
                    url:        $url,
                    action:     $action,
                    httpStatus: 200,
                    payload:    $this->responseToArray($response),
                );
            }

            return $results;

        } catch (\Throwable $e) {
            /*
            | If the batch itself fails (auth error, network error),
            | return a failure result for every URL.
            */
            return array_map(
                fn (string $url) => IndexingResult::failure(
                    engine:     self::ENGINE,
                    url:        $url,
                    action:     $action,
                    httpStatus: 0,
                    message:    'Batch failed: ' . $e->getMessage(),
                ),
                $urls,
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
        $path = $this->googleConfig['credentials_path'] ?? null;

        return ! empty($path) && file_exists($path);
    }

    public function getEngine(): string
    {
        return self::ENGINE;
    }

    /*
    |--------------------------------------------------------------------------
    | Build and configure the Google API client
    |--------------------------------------------------------------------------
    | We build a fresh client each time rather than caching it as a property
    | because the access token has a 1-hour TTL. The Google client handles
    | token refresh internally, but a long-lived singleton in a queue worker
    | context can cause subtle issues with stale state between jobs.
    */
    protected function buildGoogleClient(): GoogleClient
    {
        $client = new GoogleClient();

        $client->setAuthConfig($this->googleConfig['credentials_path']);
        $client->setScopes($this->googleConfig['scopes']);

        /*
        | Apply our HTTP timeout settings to the underlying Guzzle client
        | that google/apiclient uses internally.
        */
        $client->setHttpClient(
            new \GuzzleHttp\Client([
                'timeout'         => $this->httpConfig['timeout'],
                'connect_timeout' => $this->httpConfig['connect_timeout'],
            ])
        );

        return $client;
    }

    /*
    |--------------------------------------------------------------------------
    | Get a configured Indexing service instance
    |--------------------------------------------------------------------------
    | Cached per-request as a property for single-URL submissions.
    | Not used for batch (batch needs direct client access).
    */
    protected function getService(): Indexing
    {
        if ($this->service === null) {
            $this->service = new Indexing($this->buildGoogleClient());
        }

        return $this->service;
    }

    /*
    |--------------------------------------------------------------------------
    | Parse a Google Service Exception into a readable message
    |--------------------------------------------------------------------------
    | Google exceptions carry structured error details — extract the most
    | useful message rather than exposing raw exception text.
    */
    protected function parseGoogleException(\Google\Service\Exception $e): string
    {
        $errors = $e->getErrors();

        if (! empty($errors)) {
            $first = $errors[0];

            return implode(' — ', array_filter([
                $first['message'] ?? null,
                $first['reason']  ?? null,
            ]));
        }

        return $e->getMessage();
    }

    /*
    |--------------------------------------------------------------------------
    | Extract error array from a Google Service Exception
    |--------------------------------------------------------------------------
    */
    protected function parseGoogleExceptionErrors(\Google\Service\Exception $e): array
    {
        return [
            'errors' => $e->getErrors(),
            'code'   => $e->getCode(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Convert a Google API response object to a plain array for logging
    |--------------------------------------------------------------------------
    */
    protected function responseToArray(mixed $response): array
    {
        if (method_exists($response, 'toSimpleObject')) {
            return (array) $response->toSimpleObject();
        }

        if (method_exists($response, 'getModelName')) {
            return json_decode(json_encode($response), true) ?? [];
        }

        return [];
    }
}