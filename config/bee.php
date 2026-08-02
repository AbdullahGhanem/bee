<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bee API Credentials
    |--------------------------------------------------------------------------
    */

    'username' => env('BEE_USERNAME', ''),

    'password' => env('BEE_PASSWORD', ''),

    'url' => env('BEE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Terminal & Language
    |--------------------------------------------------------------------------
    |
    | The API requires a unique External Terminal ID per terminal (PDF FAQ
    | Q3, page 19) and a "language" on every request (missing it is API
    | error 1011).
    |
    */

    'terminal_id' => env('BEE_TERMINAL_ID', ''),

    'language' => env('BEE_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | The API returns HTTP 200 for business failures, so success is read
    | from the response body. Set false to receive the raw payload instead
    | of a thrown exception.
    |
    */

    'errors' => [
        'throw' => env('BEE_ERRORS_THROW', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic retry behavior for failed API requests.
    |
    */

    'retry' => [
        'tries' => env('BEE_RETRY_TRIES', 3),
        'delay' => env('BEE_RETRY_DELAY', 100), // milliseconds
        'multiplier' => env('BEE_RETRY_MULTIPLIER', 2), // exponential backoff multiplier
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable request/response logging for debugging and auditing.
    |
    */

    'logging' => [
        'enabled' => env('BEE_LOG_ENABLED', false),
        'channel' => env('BEE_LOG_CHANNEL', null), // null = default channel
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for service and category lists.
    |
    */

    'cache' => [
        'enabled' => env('BEE_CACHE_ENABLED', true),
        'ttl' => env('BEE_CACHE_TTL', 3600), // seconds
        'prefix' => 'bee_',
        'store' => env('BEE_CACHE_STORE', null), // null = default store
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limit the number of API requests per minute.
    |
    */

    'rate_limit' => [
        'enabled' => env('BEE_RATE_LIMIT_ENABLED', false),
        'max_attempts' => env('BEE_RATE_LIMIT_MAX', 60), // requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoint for receiving transaction status updates.
    |
    */

    'webhook' => [
        'enabled' => env('BEE_WEBHOOK_ENABLED', false),
        'path' => env('BEE_WEBHOOK_PATH', 'bee/webhook'),
        'secret' => env('BEE_WEBHOOK_SECRET', null),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for async/batch transactions.
    |
    */

    'queue' => [
        'connection' => env('BEE_QUEUE_CONNECTION', null), // null = default
        'queue' => env('BEE_QUEUE_NAME', 'default'),
    ],
];
