<?php

// config/seo-indexing.php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Engines
    |--------------------------------------------------------------------------
    | Which indexing engines to notify. Disable any engine here globally
    | without removing its credentials.
    */
    'engines' => [
        'google'   => true,
        'indexnow' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Indexing API
    |--------------------------------------------------------------------------
    | Requires a Google Service Account with the Indexing API enabled.
    | https://developers.google.com/search/apis/indexing-api/v3/quickstart
    |
    | credentials_path : absolute path to your service account JSON key file
    | scopes           : do not change, required by Google Indexing API
    */
    'google' => [
        'credentials_path' => env('GOOGLE_INDEXING_CREDENTIALS_PATH'),
        'scopes'           => [
            'https://www.googleapis.com/auth/indexing',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow
    |--------------------------------------------------------------------------
    | IndexNow is supported by Bing, Yandex, Seznam and Naver.
    | https://www.indexnow.org/documentation
    |
    | key      : your IndexNow API key (alphanumeric, min 8 chars)
    | key_file : filename of the verification file served at your domain root
    |            e.g. https://example.com/{key_file}
    |            Leave null to default to "{key}.txt"
    | host     : your site's base URL (used in IndexNow batch payloads)
    | engines  : list of IndexNow-compatible endpoints to ping
    */
    'indexnow' => [
        'key'      => env('INDEXNOW_KEY'),
        'key_file' => env('INDEXNOW_KEY_FILE', null),
        'host'     => env('APP_URL'),
        'engines'  => [
            'https://api.indexnow.org/indexnow',
            'https://www.bing.com/indexnow',
            'https://yandex.com/indexnow',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | When enabled, indexing submissions are dispatched as background jobs.
    | Set enabled to false to submit synchronously (not recommended in prod).
    */
    'queue' => [
        'enabled'    => env('SEO_INDEXING_QUEUE_ENABLED', true),
        'connection' => env('SEO_INDEXING_QUEUE_CONNECTION', 'default'),
        'name'       => env('SEO_INDEXING_QUEUE_NAME', 'indexing'),
        'retry_after' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Every API submission is logged to the database (seo_indexing_logs table).
    | Disable if you don't want persistence.
    | retention_days: automatically prune logs older than N days (0 = keep all)
    */
    'logging' => [
        'enabled'        => true,
        'retention_days' => env('SEO_INDEXING_LOG_RETENTION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    | Timeout settings for outbound API requests.
    */
    'http' => [
        'timeout'         => 30,
        'connect_timeout' => 10,
        'retry'           => [
            'times' => 3,
            'sleep' => 1000, // milliseconds between retries
        ],
    ],

];