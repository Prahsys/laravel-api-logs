<?php

namespace Prahsys\ApiLogs\Services;

use Carbon\Carbon;

class ApiLogItemService
{
    /**
     * Store an API log item record in the database.
     *
     * @return mixed The created or updated ApiLogItem model
     */
    public function storeApiLogItem(array $logData)
    {
        // Get the model class from config
        $apiLogItemClass = config('api-logs.models.api_log_item');

        $now = now();

        // Prepare the data for upsert
        $data = [
            'request_id' => $logData['request_id'],
            'path' => $logData['path'],
            'method' => $logData['method'],
            'api_version' => $logData['api_version'],
            'request_at' => Carbon::parse($logData['request_at']),
            'response_at' => isset($logData['response_at']) ? Carbon::parse($logData['response_at']) : null,
            'response_status' => $logData['response_status'] ?? null,
            'is_error' => $logData['is_error'] ?? false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Use upsert to avoid the N+1 SELECT query pattern
        // This performs INSERT ... ON CONFLICT DO UPDATE at the database level
        $apiLogItemClass::upsert(
            [$data],
            ['request_id'], // Unique identifier
            ['path', 'method', 'api_version', 'response_at', 'response_status', 'is_error', 'updated_at'] // Fields to update on conflict
        );

        // Fetch and return the record
        // Note: This is still 1 SELECT per item, but it happens AFTER the upsert
        // This is necessary to return the model instance with the correct ID
        return $apiLogItemClass::where('request_id', $logData['request_id'])->first();
    }

    /**
     * Bulk store multiple API log items in the database.
     *
     * @param  array  $logDataArray  Array of log data items
     * @return void
     */
    public function bulkStoreApiLogItems(array $logDataArray): void
    {
        if (empty($logDataArray)) {
            return;
        }

        // Get the model class from config
        $apiLogItemClass = config('api-logs.models.api_log_item');

        // Prepare all data for bulk upsert
        $dataToUpsert = [];
        $now = now();

        foreach ($logDataArray as $logData) {
            $dataToUpsert[] = [
                'request_id' => $logData['request_id'],
                'path' => $logData['path'],
                'method' => $logData['method'],
                'api_version' => $logData['api_version'],
                'request_at' => Carbon::parse($logData['request_at']),
                'response_at' => isset($logData['response_at']) ? Carbon::parse($logData['response_at']) : null,
                'response_status' => $logData['response_status'] ?? null,
                'is_error' => $logData['is_error'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk upsert all records at once (timestamps must be manually set for upsert)
        $apiLogItemClass::upsert(
            $dataToUpsert,
            ['request_id'], // Unique identifier
            ['path', 'method', 'api_version', 'response_at', 'response_status', 'is_error', 'updated_at'] // Fields to update on conflict
        );
    }
}
