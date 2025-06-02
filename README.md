# Prahsys Laravel API Logs

A Laravel package for logging API requests and responses with idempotency support.

## Features

- Logs API requests and responses to configurable logging channels in both raw and redacted formats
- Implements idempotency for API requests using the `infinitypaul/idempotency-laravel` package
- Tracks relationships between API requests and models
- Configurable redaction of sensitive data
- Support for custom models and redactors

## Installation

```bash
composer require prahsys/laravel-api-logs
```

## Configuration

Publish the configuration file and migrations:

```bash
php artisan vendor:publish --provider="Prahsys\ApiLogs\ApiLogsServiceProvider"
```

Configure your `.env` file:

```
API_LOGS_RAW_CHANNEL=api-logs-raw
API_LOGS_REDACTED_CHANNEL=api-logs-redacted
IDEMPOTENCY_TTL=86400
```

See the INTEGRATION.md file for more detailed integration instructions.
