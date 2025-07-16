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

        // Bulk insert for each model class
        foreach ($modelsByClass as $modelClass => $modelIds) {
            try {
                // Verify models exist before attaching
                $existingIds = $modelClass::whereIn('id', $modelIds)->pluck('id')->toArray();

                if (empty($existingIds)) {
                    Log::warning("No models found for {$modelClass} with IDs: ".implode(', ', $modelIds));

                    continue;
                }

                // Log missing models
                $missingIds = array_diff($modelIds, $existingIds);
                if (! empty($missingIds)) {
                    Log::warning("Models not found for {$modelClass} with IDs: ".implode(', ', $missingIds));
                }

                // Bulk attach existing models
                if (! empty($existingIds)) {
                    $apiLogItem->getRelatedModels($modelClass)->attach($existingIds);
                    Log::info("Bulk associated {$modelClass} models with API log item {$event->requestId}", [
                        'model_class' => $modelClass,
                        'model_ids' => $existingIds,
                        'count' => count($existingIds),
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Error bulk associating models with API log item: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'model_class' => $modelClass,
                    'model_ids' => $modelIds,
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
