<?php

namespace Prahsys\ApiLogs\Services;

use Prahsys\ApiLogs\Data\ApiLogData;

class ApiLogCollector
{
    /**
     * Collection of API log data to be stored in bulk
     */
    protected array $pendingLogs = [];

    /**
     * Flag to track if terminating callback is registered
     */
    protected bool $terminatingCallbackRegistered = false;

    /**
     * Add API log data to the pending collection
     */
    public function add(ApiLogData $apiLogData): void
    {
        $this->pendingLogs[] = $apiLogData;

        // Register the terminating callback only once
        if (! $this->terminatingCallbackRegistered && app()->bound('request')) {
            $this->registerTerminatingCallback();
            $this->terminatingCallbackRegistered = true;
        }
    }

    /**
     * Get all pending API logs
     */
    public function getPendingLogs(): array
    {
        return $this->pendingLogs;
    }

    /**
     * Clear all pending logs
     */
    public function clear(): void
    {
        $this->pendingLogs = [];
    }

    /**
     * Register a terminating callback to store all logs in bulk
     */
    protected function registerTerminatingCallback(): void
    {
        app()->terminating(function () {
            $this->flushLogs();
        });
    }

    /**
     * Flush all pending logs to the database
     */
    public function flushLogs(): void
    {
        if (empty($this->pendingLogs)) {
            return;
        }

        $apiLogItemService = app(ApiLogItemService::class);
        $tracker = app(ApiLogItemTracker::class);

        // Prepare log data for bulk storage
        $logDataArray = [];
        $apiLogDataMap = []; // Map request_id to ApiLogData for event firing

        foreach ($this->pendingLogs as $apiLogData) {
            $logDataArray[] = [
                'request_id' => $apiLogData->id,
                'path' => parse_url($apiLogData->url, PHP_URL_PATH) ?: '/',
                'method' => $apiLogData->method,
                'api_version' => $apiLogData->request['api_version'] ?? 'default',
                'request_at' => $apiLogData->request['timestamp'],
                'response_at' => $apiLogData->response['timestamp'],
                'response_status' => $apiLogData->statusCode,
                'is_error' => ! $apiLogData->success,
            ];

            $apiLogDataMap[$apiLogData->id] = $apiLogData;
        }

        // Bulk store all API log items at once
        try {
            $apiLogItemService->bulkStoreApiLogItems($logDataArray);

            // Fire events for each log item (events are still individual for extensibility)
            $apiLogItemClass = config('api-logs.models.api_log_item');
            foreach ($apiLogDataMap as $requestId => $apiLogData) {
                try {
                    // Fetch the stored log item
                    $apiLogItem = $apiLogItemClass::where('request_id', $requestId)->first();

                    if ($apiLogItem) {
                        \Prahsys\ApiLogs\Events\CompleteApiLogItemEvent::dispatch(
                            $requestId,
                            $apiLogItem->id,
                            $tracker,
                            $apiLogData
                        );
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error firing CompleteApiLogItemEvent: '.$e->getMessage(), [
                        'request_id' => $requestId,
                        'exception' => $e,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error bulk storing API log items: '.$e->getMessage(), [
                'exception' => $e,
                'count' => count($logDataArray),
            ]);
        }

        // Clear the pending logs
        $this->clear();
    }
}
