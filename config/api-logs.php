<?php

return [
    'enabled' => env('API_LOGS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Request Correlation Settings
    |--------------------------------------------------------------------------
    |
    | Configure request correlation/tracking for audit trails and model association.
    | Note: This provides request correlation, not true idempotency (duplicate prevention).
    |
    */
    'correlation' => [
        'header_name' => 'Idempotency-Key', // Header name for request correlation ID
        'ensure_header' => true, // Auto-generate header if missing, or skip logging if false
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound API Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure logging for outbound HTTP requests made via Guzzle/PSR clients.
    | This captures API calls your application makes to external services.
    |
    */
    'outbound' => [
        'enabled' => env('API_LOGS_OUTBOUND_ENABLED', true),
        'exclude_hosts' => [
            // Hosts to exclude from outbound logging (supports wildcards)
            // 'localhost',
            // '*.internal.company.com',
            // 'monitoring.example.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Exclusions
    |--------------------------------------------------------------------------
    |
    | Configure paths that should be excluded from API logging.
    |
    */
    'exclude_paths' => [
        // Paths to exclude from logging (supports wildcards)
        // 'health-check',
        // 'api/internal/*',
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
            \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
            \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Configure database connection and pruning for API log storage.
    |
    */
    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
        'pruning' => [
            'ttl_hours' => env('API_LOGS_TTL_HOURS', 24 * 365), // Default 365 days
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Classes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the data classes to be used by the package.
    | You can override these to use your own custom implementations.
    | Must be an extension of \Prahsys\ApiLogs\Data\ApiLogData.
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
        'api_log_item' => \Prahsys\ApiLogs\Models\ApiLogItem::class,
    ],
];
