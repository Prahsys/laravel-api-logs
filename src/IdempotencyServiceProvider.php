<?php

namespace Prahsys\ApiLogs;

use Illuminate\Support\ServiceProvider;
use Prahsys\ApiLogs\Http\Middleware\IdempotencyLogMiddleware;

class IdempotencyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the IdempotencyServiceProvider from infinitypaul/idempotency-laravel
        $this->app->register(\Infinitypaul\Idempotency\IdempotencyServiceProvider::class);

        // Override the cache key format used by the infinitypaul/idempotency-laravel package
        $this->app->singleton('idempotency-key', function ($app) {
            return config('prahsys-api-logs.idempotency.cache_prefix', 'idempotency:');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish the infinitypaul/idempotency-laravel config
        $this->publishes([
            __DIR__.'/../vendor/infinitypaul/idempotency-laravel/config/idempotency.php' => config_path('idempotency.php'),
        ], 'idempotency-config');

        // Register middleware
        $this->app->singleton(IdempotencyLogMiddleware::class);

        // Configure idempotency middleware
        $this->configureIdempotencyMiddleware();
    }

    /**
     * Configure the idempotency middleware.
     */
    protected function configureIdempotencyMiddleware(): void
    {
        // Override the idempotency header name
        config([
            'idempotency.header_name' => config('prahsys-api-logs.idempotency.header_name', 'Idempotency-Key'),
            'idempotency.ttl' => config('prahsys-api-logs.idempotency.ttl', 86400),
        ]);
    }
}
