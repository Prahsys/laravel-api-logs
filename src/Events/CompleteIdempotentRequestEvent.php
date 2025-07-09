<?php

namespace Prahsys\ApiLogs\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prahsys\ApiLogs\Data\ApiLogData;

class CompleteIdempotentRequestEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $requestId  The idempotency key/request ID
     * @param  string  $idempotentRequestId  The ID of the IdempotentRequest model
     * @param  array  $models  Array of models to associate with the idempotent request
     * @param  ApiLogData|null  $apiLogData  The API log data to process through pipelines
     */
    public function __construct(
        public string $requestId,
        public string $idempotentRequestId,
        public array $models,
        public ?ApiLogData $apiLogData = null
    ) {}
}
