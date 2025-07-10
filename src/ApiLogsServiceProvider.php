<?php

namespace Prahsys\ApiLogs;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Events\CompleteIdempotentRequestEvent;
use Prahsys\ApiLogs\Http\Middleware\IdempotencyLogMiddleware;
use Prahsys\ApiLogs\Listeners\CompleteIdempotentRequestListener;
use Prahsys\ApiLogs\Services\ModelIdempotencyTracker;

class ApiLogsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/prahsys-api-logs.php' => config_path('prahsys-api-logs.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register event listeners
        Event::listen(
            CompleteIdempotentRequestEvent::class,
            CompleteIdempotentRequestListener::class
        );
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/prahsys-api-logs.php', 'prahsys-api-logs'
        );

        // Register the middleware
        $this->app->singleton(IdempotencyLogMiddleware::class);

        // Jobs are now handled by event listeners

        // Register services
        $this->app->singleton(ModelIdempotencyTracker::class);

        // Register the configured ApiLogData class
        $dataClass = config('prahsys-api-logs.data.api_log_data', ApiLogData::class);
        $this->app->singleton($dataClass);

        // Register ApiLogPipelineManager as singleton and configure it
        $this->app->singleton(ApiLogPipelineManager::class, function ($app) {
            $manager = new ApiLogPipelineManager;

            // Load channels from config
            $channels = $app['config']->get('prahsys-api-logs.channels', []);
            $manager->loadChannels($channels);

            // Register processors on log channels
            $manager->registerProcessors();

            return $manager;
        });

        // Register the IdempotencyServiceProvider
        $this->app->register(IdempotencyServiceProvider::class);
    }
}
