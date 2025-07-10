<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Prahsys\ApiLogs\Models\ApiLogItem;
use Prahsys\ApiLogs\Services\ApiLogItemService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ApiLogItemService;
    $this->requestId = (string) Str::uuid();
});

test('it can create idempotent request record', function () {
    // Create log data
    $logData = [
        'request_id' => $this->requestId,
        'path' => 'api/test',
        'method' => 'POST',
        'api_version' => '1.0',
        'request_at' => now()->toIso8601String(),
        'response_at' => now()->addSeconds(1)->toIso8601String(),
        'response_status' => 200,
        'is_error' => false,
    ];

    // Store the idempotent request
    $idempotentRequest = $this->service->storeApiLogItem($logData);

    // Assert that the record was created
    expect($idempotentRequest)->toBeInstanceOf(ApiLogItem::class)
        ->and($idempotentRequest->request_id)->toBe($this->requestId)
        ->and($idempotentRequest->path)->toBe('api/test')
        ->and($idempotentRequest->method)->toBe('POST')
        ->and($idempotentRequest->api_version)->toBe('1.0')
        ->and($idempotentRequest->response_status)->toBe(200)
        ->and($idempotentRequest->is_error)->toBeFalse();

    // Test retrieving the record
    $retrieved = ApiLogItem::where('request_id', $this->requestId)->first();
    expect($retrieved)->not->toBeNull()
        ->and($retrieved->id)->toBe($idempotentRequest->id);
});
