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
