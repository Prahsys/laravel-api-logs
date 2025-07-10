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

        // Process each model in the array
        foreach ($event->models as $modelData) {
            try {
                $modelClass = $modelData['model_class'];
                $modelId = $modelData['model_id'];

                // Find the model
                $model = $modelClass::find($modelId);

                if (! $model) {
                    Log::warning("Model {$modelClass} with ID {$modelId} not found");

                    continue;
                }

                // Associate the model with the API log item
                $apiLogItem->getRelatedModels($modelClass)->attach($modelId);

                Log::info("Associated {$modelClass} #{$modelId} with API log item {$event->requestId}");
            } catch (\Exception $e) {
                Log::error('Error associating model with API log item: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'model_data' => $modelData,
                    'exception' => $e,
                ]);
            }
        }

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
