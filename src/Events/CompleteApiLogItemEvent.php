<?php

namespace Prahsys\ApiLogs\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prahsys\ApiLogs\Data\ApiLogData;

class CompleteApiLogItemEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $requestId  The correlation key/request ID
     * @param  string  $apiLogItemId  The ID of the ApiLogItem model
     * @param  array  $models  Array of models to associate with the API log item
     * @param  ApiLogData|null  $apiLogData  The API log data to process through pipelines
     */
    public function __construct(
        public string $requestId,
        public string $apiLogItemId,
        public array $models,
        public ?ApiLogData $apiLogData = null
    ) {}
}
