<?php

namespace Prahsys\ApiLogs\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\App;
use Prahsys\ApiLogs\Services\ApiLogItemTracker;

trait HasApiLogItems
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasApiLogItems()
    {
        // Register saved event to track models for association with API log items
        static::saved(function ($model) {
            $model->registerWithIdempotencyTracker();
        });
    }

    /**
     * Get all API log items associated with this model.
     */
    public function apiLogItems(): MorphToMany
    {
        $apiLogItemModel = config('prahsys-api-logs.models.api_log_item', ApiLogItem::class);

        return $this->morphToMany(
            $apiLogItemModel,
            'model',
            'api_log_item_models'
        );
    }

    /**
     * Get the most recent API log item for this model.
     *
     * @return ApiLogItem|null
     */
    public function latestApiLogItem()
    {
        return $this->apiLogItems()->latest('request_at')->first();
    }

    /**
     * Register this model with the ModelIdempotencyTracker service
     * This is called when the model is saved
     */
    protected function registerWithIdempotencyTracker(): void
    {
        $request = request();

        // Only proceed if we're in a web context with a request
        if (! $request) {
            return;
        }

        // Check if a correlation key is present
        $correlationKey = $request->header(
            config('prahsys-api-logs.correlation.header_name', 'Idempotency-Key')
        );

        if (! $correlationKey) {
            return;
        }

        // Register the model with the tracker
        $tracker = App::make(ApiLogItemTracker::class);
        $tracker->addModel($correlationKey, get_class($this), $this->getKey());
    }
}
