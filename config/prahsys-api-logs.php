<?php

return [
    'enabled' => env('API_LOGS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Idempotency Settings
    |--------------------------------------------------------------------------
    |
    | Configure how idempotency keys are handled and how long they are valid for.
    |
    */
    'idempotency' => [
        'header_name' => 'Idempotency-Key',
        'ttl' => env('IDEMPOTENCY_TTL', 86400), // 24 hours in seconds
        'cache_prefix' => 'idempotency:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Api Log Channels
    |--------------------------------------------------------------------------
    |
    | Configure the channels that API logs will be sent to. Each key corresponds
    | to a Laravel log channel name that must be configured in config/logging.php.
    | Each channel can have its own pipeline of redactors to process the data.
    |
    | Format: 'channel_name' => [redactor_configs...]
    |
    */
    'channels' => [
        'api_logs_raw' => [],

        'api_logs_redacted' => [
            \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
            \Prahsys\ApiLogs\Redactors\CommonBodyFields::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Configure database connection for API log storage.
    |
    */
    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Classes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the data classes to be used by the package.
    | You can override these to use your own custom implementations.
    |
    */
    'data' => [
        'api_log_data' => \Prahsys\ApiLogs\Data\ApiLogData::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the model classes to be used by the package.
    | You can override these to use your own custom implementations.
    |
    */
    'models' => [
        'idempotent_request' => \Prahsys\ApiLogs\Models\IdempotentRequest::class,
    ],
];
