<?php

// src/Clients/GoogleIndexingClient.php

namespace DancingJanissary\SeoIndexing\Clients;

use DancingJanissary\SeoIndexing\Contracts\IndexingClientContract;
use DancingJanissary\SeoIndexing\Data\IndexingResult;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Http;

class GoogleIndexingClient extends BaseClient implements IndexingClientContract
{
    protected const ENGINE       = 'google';
    protected const API_BASE_URL = 'https://indexing.googleapis.com/v3';
    protected const ACTION_UPDATED = 'URL_UPDATED';
    protected const ACTION_DELETED = 'URL_DELETED';

    protected array $googleConfig;

    public function __construct(array $googleConfig, array $httpConfig)
    {
        parent::__construct($httpConfig);
        $this->googleConfig = $googleConfig;
    }

    /*
    |--------------------------------------------------------------------------
    | Submit a single URL to Google Indexing API
    |--------------------------------------------------------------------------
    */
    public function submit(string $url, string $action): IndexingResult
    {
        try {
            $token    = $this->getAccessToken();
            $response = $this->buildHttpClient([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ])->post(self::API_BASE_URL . '/urlNotifications:publish', [
                'url'  => $url,
                'type' => $action,
            ]);

            if ($response->successful()) {
                return IndexingResult::success(
                    engine:     self::ENGINE,
                    url:        $url,
                    action:     $action,
                    httpStatus: $response->status(),
                    payload:    $response->json(),
                );
            }

            return IndexingResult::failure(
                engine:     self::ENGINE,
                url:        $url,
                action:     $action,
                httpStatus: $response->status(),
                message:    $this->parseErrorMessage($response),
                payload:    $response->json(),
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
    | Google Indexing API does not have a native batch endpoint for
    | urlNotifications:publish — each URL must be a separate request.
    | We loop and collect results.
    |
    | Note: Google enforces a quota of 200 requests/day per service account.
    | Consider this when submitting large batches.
    */
    public function submitBatch(array $urls, string $action): array
    {
        return array_map(
            fn (string $url) => $this->submit($url, $action),
            $urls
        );
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
    | OAuth2 — Get a short-lived access token from the service account key
    |--------------------------------------------------------------------------
    | ServiceAccountCredentials handles:
    | - Reading the JSON key file
    | - Signing a JWT
    | - Exchanging it for a Bearer token with Google's OAuth2 endpoint
    | - Caching the token until it expires (1 hour)
    */
    protected function getAccessToken(): string
    {
        $credentials = new ServiceAccountCredentials(
            $this->googleConfig['scopes'],
            $this->googleConfig['credentials_path'],
        );

        $token = $credentials->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new \RuntimeException(
                'Failed to obtain Google OAuth2 access token. ' .
                'Check your service account credentials file.'
            );
        }

        return $token['access_token'];
    }
}