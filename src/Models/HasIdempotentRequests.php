<?php

namespace Prahsys\ApiLogs\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\App;
use Prahsys\ApiLogs\Services\ModelIdempotencyTracker;

trait HasIdempotentRequests
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasIdempotentRequests()
    {
        // Register saved event to track models for association with idempotent requests
        static::saved(function ($model) {
            $model->registerWithIdempotencyTracker();
        });
    }

    /**
     * Get all idempotent requests associated with this model.
     */
    public function idempotentRequests(): MorphToMany
    {
        $idempotentRequestModel = config('prahsys-api-logs.models.idempotent_request', IdempotentRequest::class);

        return $this->morphToMany(
            $idempotentRequestModel,
            'model',
            'idempotent_request_models'
        );
    }

    /**
     * Get the most recent idempotent request for this model.
     *
     * @return IdempotentRequest|null
     */
    public function latestIdempotentRequest()
    {
        return $this->idempotentRequests()->latest('request_at')->first();
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

        // Check if an idempotency key is present
        $idempotencyKey = $request->header(
            config('prahsys-api-logs.idempotency.header_name', 'Idempotency-Key')
        );

        if (! $idempotencyKey) {
            return;
        }

        // Register the model with the tracker
        $tracker = App::make(ModelIdempotencyTracker::class);
        $tracker->addModel($idempotencyKey, get_class($this), $this->getKey());
    }
}
