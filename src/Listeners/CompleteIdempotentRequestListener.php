<?php

namespace Prahsys\ApiLogs\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Prahsys\ApiLogs\ApiLogPipelineManager;
use Prahsys\ApiLogs\Events\CompleteIdempotentRequestEvent;

class CompleteIdempotentRequestListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CompleteIdempotentRequestEvent $event): void
    {
        $idempotentRequestClass = config('prahsys-api-logs.models.idempotent_request');

        // Find the idempotent request record by its ID (not request_id)
        $idempotentRequest = $idempotentRequestClass::find($event->idempotentRequestId);

        if (! $idempotentRequest) {
            Log::warning("Idempotent request with ID {$event->idempotentRequestId} not found");

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

                // Associate the model with the idempotent request
                $idempotentRequest->getRelatedModels($modelClass)->attach($modelId);

                Log::info("Associated {$modelClass} #{$modelId} with idempotent request {$event->requestId}");
            } catch (\Exception $e) {
                Log::error('Error associating model with idempotent request: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'model_data' => $modelData,
                    'exception' => $e,
                ]);
            }
        }

        // Process API log data through configured pipelines
        if ($event->apiLogData) {
            try {
                $pipelineManager = app(ApiLogPipelineManager::class);
                $pipelineManager->processAndLog($event->apiLogData);
            } catch (\Exception $e) {
                Log::error('Error processing API log data through pipelines: '.$e->getMessage(), [
                    'request_id' => $event->requestId,
                    'exception' => $e,
                ]);
            }
        }
    }
}
