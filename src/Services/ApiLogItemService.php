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

        // Check if a record already exists
        $existingRecord = $apiLogItemClass::where('request_id', $logData['request_id'])->first();

        if ($existingRecord) {
            // Update the existing record with response data if not already set
            if (! $existingRecord->response_at && isset($logData['response_at'])) {
                $existingRecord->update([
                    'response_at' => Carbon::parse($logData['response_at']),
                    'response_status' => $logData['response_status'] ?? null,
                    'is_error' => $logData['is_error'] ?? false,
                ]);
            }

            return $existingRecord;
        }

        // Create a new record
        return $apiLogItemClass::create([
            'request_id' => $logData['request_id'],
            'path' => $logData['path'],
            'method' => $logData['method'],
            'api_version' => $logData['api_version'],
            'request_at' => Carbon::parse($logData['request_at']),
            'response_at' => isset($logData['response_at']) ? Carbon::parse($logData['response_at']) : null,
            'response_status' => $logData['response_status'] ?? null,
            'is_error' => $logData['is_error'] ?? false,
        ]);
    }
}
