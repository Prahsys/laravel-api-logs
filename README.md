# Prahsys Laravel API Logs

A comprehensive Laravel package for logging API requests and responses with idempotency support, model tracking, and configurable data redaction.

## Features

- **Request Correlation**: Automatic correlation ID handling for API request tracking and audit trails
- **Comprehensive Logging**: Logs API requests and responses with detailed metadata
- **Data Redaction**: Configurable pipeline-based redaction for sensitive data (PCI, PII, HIPAA, etc.)
- **Model Tracking**: Automatic association of created/updated models with API requests
- **Async Processing**: Event-driven architecture with queue support
- **Multiple Channels**: Support for raw and redacted logging channels
- **Compliance Ready**: Built-in redaction support useful for PCI DSS, SOC 2, and other compliance requirements

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

1. **ApiLogMiddleware**: Captures request/response data and manages correlation IDs
2. **CompleteApiLogItemEvent**: Dispatched after request completion
3. **CompleteApiLogItemListener**: Processes model associations and log data
4. **ApiLogPipelineManager**: Registers Monolog processors for automatic redaction
5. **ApiLogProcessor**: Monolog processor that applies redaction pipelines to log records
6. **ApiLogItemTracker**: Tracks models during request processing
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
        \Prahsys\ApiLogs\Http\Middleware\ApiLogMiddleware::class,
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

### Outbound API Logging with Guzzle

The package includes Guzzle middleware to log outbound HTTP requests your application makes to external APIs.

#### Basic Setup

Add the middleware to your Guzzle client's handler stack:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Prahsys\ApiLogs\Http\Middleware\GuzzleApiLogMiddleware;

$stack = HandlerStack::create();
$stack->push(app(GuzzleApiLogMiddleware::class));

$client = new Client([
    'handler' => $stack,
    'base_uri' => 'https://api.example.com',
    'timeout' => 30,
]);
```

#### Adding to Existing Handler Stack

If you already have a handler stack with other middleware:

```php
// Get your existing handler stack
$stack = $existingClient->getConfig('handler');

// Add API logging middleware
$stack->push(app(GuzzleApiLogMiddleware::class));
```

#### Skip Logging for Specific Requests

```php
// Skip logging by adding request option
$response = $client->get('/status', [
    'prahsys_api_logs_skip' => true
]);
```

#### Configuration

Configure outbound logging in your environment:

```env
# Enable/disable outbound API logging
API_LOGS_OUTBOUND_ENABLED=true
```

Or in `config/api-logs.php`:

```php
'outbound' => [
    'enabled' => true,
    'exclude_hosts' => [
        'localhost',
        '*.internal.company.com',
        'monitoring.example.com',
    ],
],
```

### Model Tracking

Add the `HasApiLogItems` trait to models you want to track:

```php
use Prahsys\ApiLogs\Models\HasApiLogItems;

class User extends Model
{
    use HasApiLogItems;
    
    // ... model code
}
```

Models created or updated during API requests are automatically associated with the API log item. This is particularly useful for:

- **Audit trails**: Understanding which models were affected by a specific API request
- **Impact analysis**: Tracking the full scope of changes made during a request
- **Debugging**: Identifying which models were modified when troubleshooting issues
- **Compliance**: Maintaining detailed records of data modifications for regulatory requirements
- **Data lineage**: Tracing the history of model changes back to their originating API requests

### Accessing Tracked Data

```php
// Get all API requests for a model
$user = User::find(1);
$requests = $user->apiLogItems;

// Get the latest API request for a model
$latestRequest = $user->latestApiLogItem();

// Get all models associated with an API request
$apiLogItem = ApiLogItem::where('request_id', $requestId)->first();
$users = $apiLogItem->getRelatedModels(User::class)->get();

// Example: Track all models affected by a single API request
$apiLogItem = ApiLogItem::where('request_id', 'abc-123-def')->first();

// Get all users modified in this request
$affectedUsers = $apiLogItem->getRelatedModels(User::class)->get();

// Get all orders created/updated in this request  
$affectedOrders = $apiLogItem->getRelatedModels(Order::class)->get();

// Get all affected models regardless of type
$allAffectedModels = $apiLogItem->relatedModels; // Returns collection of all associated models

// Example output for debugging or audit purposes
foreach ($allAffectedModels as $model) {
    echo "Modified {$model->getMorphClass()}: ID {$model->id}";
}
```

## Configuration Options

### Channel Configuration

Configure different redaction pipelines for different channels in `config/api-logs.php`. Each channel can have its own redaction strategy based on the destination's requirements:

```php
'channels' => [
    // Raw logs - no redaction for internal secure storage
    'api_logs_raw' => [],
    
    // General monitoring - basic redaction for security
    'api_logs_redacted' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
    ],
    
    // External monitoring services - tailored redaction
    'api_logs_sentry' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
            'paths' => ['**.email', '**.phone', '**.ssn'],
        ],
    ],
    
    'api_logs_axiom' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\DotNotationRedactor::class => [
            'paths' => ['request.body.internal_id', 'response.body.debug_info'],
        ],
    ],
],
```

### Available Redactors

- `CommonHeaderFieldsRedactor`: Redacts authentication headers (extends DotNotationRedactor)
- `CommonBodyFieldsRedactor`: Redacts password fields (extends DotNotationRedactor)
- `DotNotationRedactor`: Base redactor using dot notation (supports `*` and `**` wildcards)

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

### Creating Custom Redactors

The easiest way to create custom redactors is by extending `DotNotationRedactor`, just like the built-in `CommonHeaderFieldsRedactor` and `CommonBodyFieldsRedactor`:

#### Example: PCI DSS Redactor

```php
<?php

namespace App\Redactors;

use Prahsys\ApiLogs\Redactors\DotNotationRedactor;

class PciRedactor extends DotNotationRedactor
{
    public function __construct(array $additionalPaths = [], string|\Closure $replacement = '[REDACTED]')
    {
        $pciPaths = [
            // Credit card numbers
            '**.card.number',
            '**.card_number',
            '**.cc_number',
            
            // CVV codes
            '**.card.cvv',
            '**.card.cvc',
            '**.cvv',
            '**.cvc',
            
            // Expiry dates
            '**.card.expiry',
            '**.card.exp_month',
            '**.card.exp_year',
            
            // Track data
            '**.track1',
            '**.track2',
            '**.magnetic_stripe',
        ];

        parent::__construct(
            array_merge($pciPaths, $additionalPaths),
            $replacement
        );
    }
}
```

#### Example: Healthcare (HIPAA) Redactor

```php
<?php

namespace App\Redactors;

use Prahsys\ApiLogs\Redactors\DotNotationRedactor;

class HipaaRedactor extends DotNotationRedactor
{
    public function __construct(array $additionalPaths = [], string|\Closure $replacement = '[REDACTED]')
    {
        $hipaaPaths = [
            // Patient identifiers
            '**.patient.ssn',
            '**.patient.medical_record_number',
            '**.patient.account_number',
            '**.patient.insurance_id',
            
            // Biometric data
            '**.biometric',
            '**.fingerprint',
            '**.voice_print',
            
            // Health information
            '**.diagnosis',
            '**.medical_condition',
            '**.treatment',
            '**.medication',
            
            // Deep wildcard patterns for nested patient data
            '**.patient.**.personal_id',
            '**.health_record.**',
        ];

        parent::__construct(
            array_merge($hipaaPaths, $additionalPaths),
            $replacement
        );
    }
}
```

#### Example: General PII Redactor

```php
<?php

namespace App\Redactors;

use Prahsys\ApiLogs\Redactors\DotNotationRedactor;

class PiiRedactor extends DotNotationRedactor
{
    public function __construct(array $additionalPaths = [], string|\Closure $replacement = '[REDACTED]')
    {
        $piiPaths = [
            // Personal identifiers
            '**.ssn',
            '**.social_security_number',
            '**.sin',
            '**.national_id',
            '**.passport_number',
            '**.drivers_license',
            
            // Contact information
            '**.email',
            '**.phone',
            '**.phone_number',
            '**.mobile',
            '**.address',
            '**.street_address',
            '**.postal_code',
            '**.zip_code',
            
            // Financial information
            '**.bank_account',
            '**.routing_number',
            '**.iban',
            '**.account_number',
            
            // Deep patterns for user objects
            '**.user.email',
            '**.user.phone',
            '**.users.*.email',
            '**.users.*.phone',
        ];

        parent::__construct(
            array_merge($piiPaths, $additionalPaths),
            $replacement
        );
    }
}
```

#### Advanced: Custom Replacement Logic

You can also provide custom replacement logic using closures:

```php
<?php

namespace App\Redactors;

use Prahsys\ApiLogs\Redactors\DotNotationRedactor;

class SmartRedactor extends DotNotationRedactor
{
    public function __construct()
    {
        $paths = ['**.card.number', '**.email'];
        
        $customReplacement = function ($value, $path) {
            if (str_contains($path, 'card.number')) {
                // Show only last 4 digits of card numbers
                return '****-****-****-' . substr($value, -4);
            }
            
            if (str_contains($path, 'email')) {
                // Partially redact email addresses
                [$local, $domain] = explode('@', $value);
                return substr($local, 0, 2) . '***@' . $domain;
            }
            
            return '[REDACTED]';
        };

        parent::__construct($paths, $customReplacement);
    }
}
```

#### Using Custom Redactors

Once created, use your custom redactors in your channel configuration:

```php
// config/api-logs.php
'channels' => [
    'api_logs_pci_compliant' => [
        \App\Redactors\PciRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
    ],
    
    'api_logs_healthcare' => [
        \App\Redactors\HipaaRedactor::class,
        \App\Redactors\PiiRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
    ],
],
```

## Event System

### Listening to Events

You can listen to `CompleteApiLogItemEvent` to add custom processing:

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
- `requestId`: The correlation ID
- `apiLogItemId`: Database ID of the ApiLogItem
- `models`: Array of associated models
- `apiLogData`: Complete API log data object

## Compliance Features

This package provides features that are generally useful for compliance requirements:

### PCI DSS Support

- Comprehensive audit trails with detailed metadata
- Data isolation through configurable redaction system
- Transaction traceability via correlation IDs
- Protected logging channels with access controls

### SOC 2 Support

- Audit-ready logging for security events
- Clear system boundaries through redaction
- Consistent logging format for monitoring
- Data protection through configurable redaction

## External Service Integration

### Monitoring and Alerting Services

The package integrates seamlessly with external monitoring services:

**Sentry Integration:**

```php
// config/prahsys-api-logs.php
'channels' => [
    'api_logs_sentry' => [
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
        \App\Redactors\PiiRedactor::class, // Custom PII redactor
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
    \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
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
        \App\Redactors\PciRedactor::class, // Custom PCI redactor
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
    ],
    
    'api_logs_pci_monitoring' => [
        \App\Redactors\PciRedactor::class, // Custom PCI redactor
        \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
        \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
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
    'days' => 2555, // Extended retention as needed
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
