<?php

namespace Prahsys\ApiLogs;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Events\CompleteApiLogItemEvent;
use Prahsys\ApiLogs\Http\Middleware\ApiLogMiddleware;
use Prahsys\ApiLogs\Http\Middleware\GuzzleApiLogMiddleware;
use Prahsys\ApiLogs\Listeners\CompleteApiLogItemListener;
use Prahsys\ApiLogs\Services\ApiLogItemTracker;

class ApiLogsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/api-logs.php' => config_path('api-logs.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register event listeners
        Event::listen(
            CompleteApiLogItemEvent::class,
            CompleteApiLogItemListener::class
        );
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/api-logs.php', 'api-logs'
        );

        // Register the middleware
        $this->app->singleton(ApiLogMiddleware::class);

        // Register Guzzle middleware (not singleton - multiple outbound calls possible)
        $this->app->bind(GuzzleApiLogMiddleware::class);

        // Jobs are now handled by event listeners

        // Register services
        $this->app->singleton(ApiLogItemTracker::class);

        // Register the configured ApiLogData class
        $dataClass = config('api-logs.data.api_log_data', ApiLogData::class);
        $this->app->singleton($dataClass);

        // Register ApiLogPipelineManager as singleton and configure it
        $this->app->singleton(ApiLogPipelineManager::class, function ($app) {
            $manager = new ApiLogPipelineManager;

            // Load channels from config
            $channels = $app['config']->get('api-logs.channels', []);
            $manager->loadChannels($channels);

            // Register processors on log channels
            $manager->registerProcessors();

            return $manager;
        });

    }
}
