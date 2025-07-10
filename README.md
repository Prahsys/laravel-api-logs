# Prahsys Laravel API Logs

A comprehensive Laravel package for logging API requests and responses with idempotency support, model tracking, and configurable data redaction.

## Features

- **Request Correlation**: Automatic correlation ID handling for API request tracking and audit trails
- **Comprehensive Logging**: Logs API requests and responses with detailed metadata
- **Data Redaction**: Configurable pipeline-based redaction for sensitive data (PCI, PII, HIPAA, etc.)
- **Model Tracking**: Automatic association of created/updated models with API requests
- **Async Processing**: Event-driven architecture with queue support
- **Multiple Channels**: Support for raw and redacted logging channels
- **Compliance Ready**: Built-in support for common PCI DSS and SOC 2 fields

## Architecture Overview

The package follows a clean event-driven architecture with a **lightweight database design**:

```
HTTP Request → Middleware → Event → Listener → Pipeline → Log Channels
                    ↓
              Model Tracking → Association → Database (Lightweight References)
```

### Design Philosophy

**ApiLogItem** is designed as a **lightweight reference** to API requests, not a full data store. This approach:

- **Keeps database lean**: Stores only essential metadata (correlation ID, path, method, timestamps, status)
- **Enables long-term retention**: Default 365-day database retention for audit trails
- **Separates concerns**: Heavy request/response data goes to log channels, references stay in database
- **Maximizes flexibility**: Users can extend the model to store additional data if needed

The actual request/response data is processed through configurable log channels where it can be:
- Stored in log files with native Laravel rotation
- Sent to external services (Axiom, Sentry, etc.) 
- Redacted according to compliance requirements
- Retained for different periods per channel

### Key Components

1. **IdempotencyLogMiddleware**: Captures request/response data and manages correlation IDs
2. **CompleteIdempotentRequestEvent**: Dispatched after request completion
3. **CompleteIdempotentRequestListener**: Processes model associations and log data
4. **ApiLogPipelineManager**: Registers Monolog processors for automatic redaction
5. **ApiLogProcessor**: Monolog processor that applies redaction pipelines to log records
6. **ModelIdempotencyTracker**: Tracks models during request processing
7. **Redaction System**: Pipeline-based data redaction with configurable redactors

## Installation

```bash
composer require prahsys/laravel-api-logs
```

## Configuration

### 1. Publish Configuration and Migrations

```bash
php artisan vendor:publish --provider="Prahsys\ApiLogs\ApiLogsServiceProvider"
php artisan migrate
```

### 2. Environment Configuration

```env
# API Logs Settings  
API_LOGS_TTL=86400

# Logging Channels (configure in config/logging.php)
API_LOGS_RAW_CHANNEL=api_logs_raw
API_LOGS_REDACTED_CHANNEL=api_logs_redacted
```

### 3. Logging Channels

Add to your `config/logging.php`:

```php
'channels' => [
    // Raw logs - restricted access, complete data
    'api_logs_raw' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api_logs_raw.log'),
        'level' => 'info',
        'days' => 14,
        'permission' => 0600, // Restricted access
    ],
    
    // Redacted logs - general monitoring and analytics
    'api_logs_redacted' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api_logs_redacted.log'),
        'level' => 'info',
        'days' => 90,
    ],
    
    // External monitoring services with tailored redaction
    'api_logs_sentry' => [
        'driver' => 'sentry',
        'level' => 'error',
        'bubble' => true,
    ],
    
    'api_logs_axiom' => [
        'driver' => 'custom',
        'via' => App\Logging\AxiomLogger::class,
        'level' => 'info',
        'dataset' => 'api_logs',
    ],
    
    // Stack multiple channels for comprehensive monitoring
    'api_logs_monitoring' => [
        'driver' => 'stack',
        'channels' => ['api_logs_redacted', 'api_logs_sentry'],
        'ignore_exceptions' => false,
    ],
],
```

### 4. Middleware Registration

Add to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        // ... other middleware
        \Prahsys\ApiLogs\Http\Middleware\IdempotencyLogMiddleware::class,
    ],
];
```

## Usage

### Basic Usage

Once configured, the package automatically logs API requests. Include an `Idempotency-Key` header for request correlation:

```php
$response = Http::withHeaders([
    'Idempotency-Key' => 'unique-key-123',
])->post('/api/users', ['name' => 'John Doe']);
```

### Model Tracking

Add the `HasIdempotentRequests` trait to models you want to track:

```php
use Prahsys\ApiLogs\Models\HasIdempotentRequests;

class User extends Model
{
    use HasIdempotentRequests;
    
    // ... model code
}
```

Models created or updated during API requests are automatically associated with the idempotent request.

### Accessing Tracked Data

```php
// Get all API requests for a model
$user = User::find(1);
$requests = $user->idempotentRequests;

// Get the latest API request for a model
$latestRequest = $user->latestIdempotentRequest();

// Get all models associated with an API request
$idempotentRequest = IdempotentRequest::where('request_id', $requestId)->first();
$users = $idempotentRequest->getRelatedModels(User::class)->get();
```

## Configuration Options

### Channel Configuration

Configure different redaction pipelines for different channels in `config/prahsys-api-logs.php`. Each channel can have its own redaction strategy based on the destination's requirements:

```php
'channels' => [
    // Raw logs - no redaction for internal secure storage
    'api_logs_raw' => [],
    
    // General monitoring - basic redaction for security
    'api_logs_redacted' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFields::class,
    ],
    
    // External monitoring services - tailored redaction
    'api_logs_sentry' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFields::class,
        \Prahsys\ApiLogs\Redactors\PiiRedactor::class, // Extra privacy for external service
    ],
    
    'api_logs_axiom' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFields::class,
        \Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
            'paths' => ['request.body.internal_id', 'response.body.debug_info'],
        ],
    ],
    
    // Compliance-specific channels
    'api_logs_pci' => [
        \Prahsys\ApiLogs\Redactors\PciRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
    ],
    
    'api_logs_hipaa' => [
        \Prahsys\ApiLogs\Redactors\HipaaRedactor::class,
        \Prahsys\ApiLogs\Redactors\PiiRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
    ],
],
```

### Available Redactors

- `CommonHeaderFields`: Redacts authentication headers
- `CommonBodyFields`: Redacts password fields
- `DotNotationRedactor`: Redacts fields using dot notation (supports `*` and `**` wildcards)
- `PiiRedactor`: Redacts personally identifiable information
- `HipaaRedactor`: Redacts HIPAA-protected health information
- `PciRedactor`: Redacts PCI DSS sensitive payment data

### Wildcard Pattern Support

The `DotNotationRedactor` supports powerful wildcard patterns:

- **Single wildcard (`*`)**: Matches one level
  ```php
  'users.*.email' // Matches users.1.email, users.john.email, etc.
  ```

- **Deep wildcard (`**`)**: Matches any level of nesting
  ```php
  '**.card.number' // Matches card.number anywhere in the data structure
  '**.password'    // Matches password fields at any depth
  ```

Examples:
```php
// Traditional specific paths
\Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
    'paths' => ['request.body.users.0.password', 'request.body.users.1.password'],
]

// Using single wildcards  
\Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
    'paths' => ['request.body.users.*.password'],
]

// Using deep wildcards for complex nested data
\Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
    'paths' => ['**.password', '**.card.number', '**.ssn'],
]
```

### Extending ApiLogItem for Custom Data Storage

The default `ApiLogItem` stores lightweight references. You can extend it to store additional data:

```php
// Create custom model
class CustomApiLogItem extends \Prahsys\ApiLogs\Models\ApiLogItem
{
    protected $fillable = [
        // Default fields
        'request_id', 'path', 'method', 'api_version', 
        'request_at', 'response_at', 'response_status', 'is_error',
        
        // Custom fields
        'request_payload', 'response_payload', 'user_id', 'client_ip'
    ];
    
    protected $casts = [
        // Default casts
        'request_at' => 'datetime:Y-m-d H:i:s.v',
        'response_at' => 'datetime:Y-m-d H:i:s.v',
        'response_status' => 'integer',
        'is_error' => 'boolean',
        
        // Custom casts
        'request_payload' => 'json',
        'response_payload' => 'json',
    ];
}

// Create custom migration
Schema::table('api_log_items', function (Blueprint $table) {
    $table->json('request_payload')->nullable();
    $table->json('response_payload')->nullable();
    $table->string('user_id')->nullable();
    $table->string('client_ip')->nullable();
});

// Update config
'models' => [
    'api_log_item' => \App\Models\CustomApiLogItem::class,
],
```

**Alternative approaches:**
- **External correlation**: Use correlation IDs to fetch full data from Axiom/Elasticsearch
- **Hybrid storage**: Store critical fields in database, full payloads in object storage
- **Event sourcing**: Store lightweight events, reconstruct full state when needed

### Custom Redactors

Create custom redactors by implementing `RedactorInterface`:

```php
use Prahsys\ApiLogs\Contracts\RedactorInterface;

class CustomRedactor implements RedactorInterface
{
    public function handle(array $data, \Closure $next): array
    {
        // Redact sensitive data
        $data = $this->redactSensitiveFields($data);
        
        return $next($data);
    }
    
    private function redactSensitiveFields(array $data): array
    {
        // Implementation
        return $data;
    }
}
```

## Event System

### Listening to Events

You can listen to `CompleteIdempotentRequestEvent` to add custom processing:

```php
use Prahsys\ApiLogs\Events\CompleteApiLogItemEvent;

// In your EventServiceProvider
protected $listen = [
    CompleteApiLogItemEvent::class => [
        YourCustomListener::class,
    ],
];
```

### Event Data

The event contains:
- `requestId`: The idempotency key
- `idempotentRequestId`: Database ID of the IdempotentRequest
- `models`: Array of associated models
- `apiLogData`: Complete API log data object

## Compliance Features

### PCI DSS Compliance

- **Requirement 10.2 & 10.3**: Comprehensive audit trails with detailed metadata
- **Requirement 3.4**: Data isolation through redaction system
- **Requirement 10.3.4**: Transaction traceability via idempotency keys
- **Requirement 10.5**: Protected logging channels with access controls

### SOC 2 Compliance

- **CC5.2**: Audit-ready logging for security events
- **CC3.1**: Clear system boundaries through redaction
- **CC7.2**: Consistent logging format for anomaly detection
- **P1.1**: Data protection through configurable redaction

## External Service Integration

### Monitoring and Alerting Services

The package integrates seamlessly with external monitoring services:

**Sentry Integration:**
```php
// config/prahsys-api-logs.php
'channels' => [
    'api_logs_sentry' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
        \Prahsys\ApiLogs\Redactors\PiiRedactor::class,
    ],
],

// config/logging.php
'api_logs_sentry' => [
    'driver' => 'sentry',
    'level' => 'error',
    'bubble' => true,
],
```

**Axiom Integration:**
```php
// Custom logger for Axiom
class AxiomLogger
{
    public function __invoke(array $config)
    {
        return new AxiomHandler($config['dataset']);
    }
}

// Channel configuration with Axiom-specific redaction
'api_logs_axiom' => [
    \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
    \Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
        'paths' => ['request.body.internal_metrics'],
    ],
],
```

**Other Services:**
- **Datadog**: Use custom handlers for structured logging
- **New Relic**: Configure with appropriate redaction for APM integration
- **Splunk**: Set up with compliance-specific redaction pipelines
- **Elasticsearch**: Use stack channels for search and analytics

### Alert Configuration

Configure different alert thresholds per channel:

```php
// Example: Route errors to Sentry, success metrics to Axiom
'channels' => [
    'api_logs_alerts' => [
        'driver' => 'stack',
        'channels' => ['api_logs_sentry'], // Only errors
    ],
    
    'api_logs_metrics' => [
        'driver' => 'stack', 
        'channels' => ['api_logs_axiom'], // All requests for analytics
    ],
],
```

## Database Pruning and Log Management

### Database Pruning

ApiLogItems are designed for long-term retention (365 days by default) but can be pruned using Laravel's built-in model pruning:

```bash
# Run model pruning manually
php artisan model:prune

# Schedule in your app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('model:prune')->daily();
}
```

Configure retention in your environment:
```env
# Retain database references for 1 year (default)
API_LOGS_TTL_HOURS=8760

# Or configure in config file
'database' => [
    'pruning' => [
        'ttl_hours' => 24 * 365, // 365 days
    ],
],
```

### Log File Management

**Separate from database retention**, configure log rotation per channel:

```php
// config/logging.php
'api_logs_raw' => [
    'driver' => 'daily',
    'path' => storage_path('logs/api_logs_raw.log'),
    'days' => 14, // Rotate log files every 14 days
],

'api_logs_redacted' => [
    'driver' => 'daily', 
    'days' => 90, // Keep redacted logs longer for analytics
],
```

**Best practices:**
- **Database**: Long retention (365+ days) for audit trails and correlation
- **Raw logs**: Short retention (7-30 days) for debugging, restricted access
- **Redacted logs**: Medium retention (30-90 days) for monitoring and analytics
- **External services**: Per-service retention policies (Sentry 30 days, Axiom 1 year, etc.)

## Performance Considerations

- **Lightweight database**: Only essential metadata stored in database
- **Async Processing**: Heavy processing is handled by queued event listeners
- **Configurable Logging**: Exclude paths and request types to reduce overhead
- **Efficient Model Tracking**: In-memory tracking during request lifecycle

## Testing

Run the test suite:

```bash
vendor/bin/pest
```

## Compliance Configuration Examples

### PCI DSS Configuration

For environments handling payment card data, configure appropriate redaction and retention:

```php
// Enhanced channel configuration for PCI environments
'channels' => [
    'api_logs_pci_raw' => [
        \Prahsys\ApiLogs\Redactors\PciRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
    ],
    
    'api_logs_pci_monitoring' => [
        \Prahsys\ApiLogs\Redactors\PciRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonHeaderFields::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFields::class,
    ],
],

// Logging channel with appropriate retention
// config/logging.php
'api_logs_pci_raw' => [
    'driver' => 'daily',
    'path' => storage_path('logs/pci/api_logs_raw.log'),
    'level' => 'info',
    'days' => 365, // Extended retention for audit requirements
    'permission' => 0600, // Restricted access
],
```

### SOC 2 Configuration

For SOC 2 environments, configure comprehensive logging with security controls:

```php
// SIEM integration for SOC 2 requirements
// config/logging.php
'channels' => [
    'api_logs_soc2' => [
        'driver' => 'stack',
        'channels' => ['syslog', 'siem_service'],
        'ignore_exceptions' => false,
    ],
    
    'syslog' => [
        'driver' => 'syslog',
        'level' => 'info',
        'facility' => LOG_USER,
    ],
],
```

### Data Retention Policies

Configure retention policies based on your compliance requirements:

```php
// Example retention configuration
// config/logging.php
'api_logs_compliance' => [
    'driver' => 'daily',
    'path' => storage_path('logs/compliance/api_logs.log'),
    'days' => 2555, // 7 years for financial data
    'permission' => 0600,
],
```

## Security Notes

- Raw logs should be stored with restricted access permissions
- Consider using separate database connections for different sensitivity levels
- Implement proper log rotation and retention policies
- Review redactor configurations for your specific compliance requirements
- Ensure appropriate access controls for different log sensitivity levels
- Consider encrypting log storage for highly sensitive environments

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This package is open-sourced software licensed under the MIT license.

## Support

For support, please open an issue on the GitHub repository or contact the maintainers.
