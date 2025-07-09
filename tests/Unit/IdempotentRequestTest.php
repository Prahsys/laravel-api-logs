<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Prahsys\ApiLogs\Models\IdempotentRequest;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->requestId = (string) Str::uuid();
});

test('it can calculate duration in milliseconds', function () {
    // Create a request that took 500ms
    $requestTime = Carbon::now();
    $responseTime = $requestTime->copy()->addMilliseconds(500);

    $idempotentRequest = new IdempotentRequest([
        'request_id' => (string) Str::uuid(),
        'request_at' => $requestTime,
        'response_at' => $responseTime,
    ]);

    // Assert duration is calculated correctly
    expect($idempotentRequest->duration_ms)->toEqual($responseTime->diff($requestTime)->milliseconds)
        ->and($idempotentRequest->getDurationFormatted())->toContain('ms');
});

test('it formats duration in seconds for longer requests', function () {
    // Create a request that took 2.5 seconds
    $requestTime = Carbon::now();
    $responseTime = $requestTime->copy()->addSeconds(2.5);

    $idempotentRequest = new IdempotentRequest([
        'request_id' => (string) Str::uuid(),
        'request_at' => $requestTime,
        'response_at' => $responseTime,
    ]);

    // Assert duration is calculated correctly
    expect($idempotentRequest->duration_ms)->toEqual($responseTime->diff($requestTime)->milliseconds)
        ->and($idempotentRequest->getDurationFormatted())->toContain('s');
});

test('it handles null duration when request or response times are missing', function () {
    // Create a request with missing response time
    $idempotentRequest = new IdempotentRequest([
        'request_id' => (string) Str::uuid(),
        'request_at' => now(),
        'response_at' => null,
    ]);

    // Assert duration handling
    expect($idempotentRequest->duration_ms)->toBeNull()
        ->and($idempotentRequest->getDurationFormatted())->toBeNull();
});

test('it can associate related models through morphToMany relationship', function () {
    // Create an idempotent request
    $idempotentRequest = IdempotentRequest::create([
        'request_id' => $this->requestId,
        'path' => 'api/test',
        'method' => 'POST',
        'api_version' => '1.0',
        'request_at' => now(),
        'response_at' => now()->addSeconds(1),
        'response_status' => 200,
        'is_error' => false,
    ]);

    // Define a mock model class
    $modelClass = 'Prahsys\\ApiLogs\\Tests\\Models\\TestModel';

    // Get the relationship
    $relation = $idempotentRequest->getRelatedModels($modelClass);

    // Verify the relationship is set up correctly
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class)
        ->and($relation->getMorphType())->toBe('model_type')
        ->and($relation->getRelated()->getMorphClass())->toBe($modelClass);
});
