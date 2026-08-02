<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Basata API Credentials
    |--------------------------------------------------------------------------
    */

    'username' => env('BASATA_USERNAME', ''),

    'password' => env('BASATA_PASSWORD', ''),

    'url' => env('BASATA_URL', ''),

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

    'terminal_id' => env('BASATA_TERMINAL_ID', ''),

    'language' => env('BASATA_LANGUAGE', 'en'),

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
        'throw' => env('BASATA_ERRORS_THROW', true),
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
        'tries' => env('BASATA_RETRY_TRIES', 3),
        'delay' => env('BASATA_RETRY_DELAY', 100), // milliseconds
        'multiplier' => env('BASATA_RETRY_MULTIPLIER', 2), // exponential backoff multiplier
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
        'enabled' => env('BASATA_LOG_ENABLED', false),
        'channel' => env('BASATA_LOG_CHANNEL', null), // null = default channel

        /*
        | Keys whose VALUES are masked in the request and response logs (and
        | in the error payload handed back to the caller). Matching is a
        | case-insensitive substring test against the key name, and against
        | the `key` of a {"key": …, "value": …} pair — which is how the API
        | carries voucher secrets: `details_list` returns the voucher PIN and
        | expiry date (FAQ A10) and `input_parameter_list` carries `card_data`
        | (5.9). The value is replaced, not dropped, so the log still shows
        | what was sent and received. Add your own service's parameter names
        | here; removing an entry un-redacts it.
        */
        'redact' => [
            'pin',
            'card',
            'voucher',
            'serial',
            'secret',
            'password',
            'expiry',
            'account_number',
        ],
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
        'enabled' => env('BASATA_CACHE_ENABLED', true),
        'ttl' => env('BASATA_CACHE_TTL', 3600), // seconds
        'prefix' => 'basata_',
        'store' => env('BASATA_CACHE_STORE', null), // null = default store
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
        'enabled' => env('BASATA_RATE_LIMIT_ENABLED', false),
        'max_attempts' => env('BASATA_RATE_LIMIT_MAX', 60), // requests per minute
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
        'enabled' => env('BASATA_WEBHOOK_ENABLED', false),
        'path' => env('BASATA_WEBHOOK_PATH', 'basata/webhook'),
        'secret' => env('BASATA_WEBHOOK_SECRET', null),
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
        'connection' => env('BASATA_QUEUE_CONNECTION', null), // null = default
        'queue' => env('BASATA_QUEUE_NAME', 'default'),
    ],
];
