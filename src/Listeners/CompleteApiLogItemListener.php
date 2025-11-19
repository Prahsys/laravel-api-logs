<?php

namespace Prahsys\ApiLogs\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Prahsys\ApiLogs\ApiLogPipelineManager;
use Prahsys\ApiLogs\Events\CompleteApiLogItemEvent;

class CompleteApiLogItemListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CompleteApiLogItemEvent $event): void
    {
        $apiLogItemClass = config('api-logs.models.api_log_item');

        // Find the API log item record by its ID (not request_id)
        $apiLogItem = $apiLogItemClass::find($event->apiLogItemId);

        if (! $apiLogItem) {
            Log::warning("API log item with ID {$event->apiLogItemId} not found");

            return;
        }

        // Collect all models for bulk insert
        $modelsByClass = [];
        $requestIds = $event->tracker->getRequestIds();

        foreach ($requestIds as $requestId) {
            $models = $event->tracker->getModelsForRequest($requestId);

            foreach ($models as $modelData) {
                $modelClass = $modelData['model_class'];
                $modelId = $modelData['model_id'];

                if (! isset($modelsByClass[$modelClass])) {
                    $modelsByClass[$modelClass] = [];
                }

                // Add to collection, avoiding duplicates
                if (! in_array($modelId, $modelsByClass[$modelClass])) {
                    $modelsByClass[$modelClass][] = $modelId;
                }
            }
        }

        // Build bulk insert data for all model types at once
        $bulkInsertData = [];
        $now = now();

        foreach ($modelsByClass as $modelClass => $modelIds) {
            try {
                // Verify models exist before attaching
                $existingIds = $modelClass::whereIn($modelClass::make()->getKeyName(), $modelIds)->pluck($modelClass::make()->getKeyName())->toArray();

                if (empty($existingIds)) {
                    Log::warning("No models found for {$modelClass} with IDs: ".implode(', ', $modelIds));
                    continue;
                }

                // Log missing models
                $missingIds = array_diff($modelIds, $existingIds);
                if (! empty($missingIds)) {
                    Log::warning("Models not found for {$modelClass} with IDs: ".implode(', ', $missingIds));
                }

                // Get morph type from morph map or use class name
                $morphType = array_search($modelClass, \Illuminate\Database\Eloquent\Relations\Relation::morphMap()) ?: $modelClass;

                // Prepare bulk insert data for this model class
                foreach ($existingIds as $modelId) {
                    $bulkInsertData[] = [
                        'api_log_item_id' => $apiLogItem->id,
                        'model_type' => $morphType,
                        'model_id' => (string) $modelId, // Cast to string to handle both UUID and non-UUID PKs
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                Log::info("Prepared {$modelClass} models for bulk association with API log item {$event->requestId}", [
                    'model_class' => $modelClass,
                    'model_ids' => $existingIds,
                    'count' => count($existingIds),
                ]);

            } catch (\Exception $e) {
                Log::error('Error preparing models for bulk association: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'model_class' => $modelClass,
                    'model_ids' => $modelIds,
                    'exception' => $e,
                ]);
            }
        }

        // Single bulk upsert for ALL model associations at once
        if (! empty($bulkInsertData)) {
            try {
                // Use the ApiLogItem model's connection to respect multi-tenancy/connection config
                $apiLogItem->getConnection()->table('api_log_item_models')->upsert(
                    $bulkInsertData,
                    ['api_log_item_id', 'model_type', 'model_id'], // Unique keys
                    ['updated_at'] // Update timestamp on conflict
                );
                Log::info("Bulk associated all models with API log item {$event->requestId}", [
                    'total_associations' => count($bulkInsertData),
                ]);
            } catch (\Exception $e) {
                Log::error('Error bulk upserting model associations: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'exception' => $e,
                ]);
            }
        }

        // Clear the tracker after processing all requests
        $event->tracker->clearAll();

        // Log API log data through configured pipelines
        if ($event->apiLogData) {
            try {
                $pipelineManager = app(ApiLogPipelineManager::class);
                $pipelineManager->log($event->apiLogData);
            } catch (\Exception $e) {
                Log::error('Error logging API log data through pipelines: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'exception' => $e,
                ]);
            }
        }
    }
}
