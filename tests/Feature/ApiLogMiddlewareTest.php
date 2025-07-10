<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Events\CompleteApiLogItemEvent;
use Prahsys\ApiLogs\Http\Middleware\ApiLogMiddleware;
use Prahsys\ApiLogs\Models\ApiLogItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    $this->middleware = app(ApiLogMiddleware::class);
    $this->idempotencyKey = (string) Str::uuid();
});

test('middleware handles request and logs data correctly', function () {
    // Create a mock request
    $request = Request::create('/api/test', 'POST', ['test_data' => 'value']);
    $request->headers->set('Idempotency-Key', $this->idempotencyKey);
    $request->headers->set('Accept-Version', '2.0');

    // Create a mock response
    $response = new Response(json_encode(['success' => true]), 200, ['Content-Type' => 'application/json']);

    // Create a mock closure that returns the response
    $next = function ($req) use ($response) {
        return $response;
    };

    // Run the middleware
    $result = $this->middleware->handle($request, $next);

    // Assert the response is correct
    expect($result)->toBe($response);

    // Check that the idempotency key was set in the request
    expect($request->header('Idempotency-Key'))->toBe($this->idempotencyKey);

    // Check that the ApiLogData was stored in the request attributes
    expect($request->attributes->has('api_log_data'))->toBeTrue();

    $apiLogData = $request->attributes->get('api_log_data');
    expect($apiLogData)->toBeInstanceOf(ApiLogData::class)
        ->and($apiLogData->id)->toBe($this->idempotencyKey)
        ->and($apiLogData->method)->toBe('POST')
        ->and($apiLogData->url)->toBe('http://localhost/api/test')
        ->and($apiLogData->request['api_version'])->toBe('2.0');

    // Run the terminate method
    $this->middleware->terminate($request, $response);

    // Check that ApiLogData was completed with response data
    expect($apiLogData->statusCode)->toBe(200)
        ->and($apiLogData->success)->toBeTrue()
        ->and($apiLogData->response)->toHaveKey('headers')
        ->and($apiLogData->response)->toHaveKey('body')
        ->and($apiLogData->response)->toHaveKey('timestamp');

    // Check that the event was fired with the ApiLogData
    Event::assertDispatched(CompleteApiLogItemEvent::class, function ($event) {
        return $event->requestId === $this->idempotencyKey
            && $event->apiLogData instanceof ApiLogData
            && $event->apiLogData->id === $this->idempotencyKey;
    });

    // Check that the idempotent request was stored in the database
    $apiLogItem = ApiLogItem::where('request_id', $this->idempotencyKey)->first();
    expect($apiLogItem)->not->toBeNull()
        ->and($apiLogItem->path)->toBe('/api/test')
        ->and($apiLogItem->method)->toBe('POST')
        ->and($apiLogItem->api_version)->toBe('2.0')
        ->and($apiLogItem->response_status)->toBe(200)
        ->and($apiLogItem->is_error)->toBeFalse();
});

test('middleware skips logging for excluded paths', function () {
    // Configure excluded paths
    config(['api-logs.exclude_paths' => ['api/docs', 'api/health']]);

    // Create a mock request to an excluded path
    $request = Request::create('/api/docs', 'GET');
    $request->headers->set('Idempotency-Key', $this->idempotencyKey);

    // Create a mock response
    $response = new Response('Docs content', 200);

    // Create a mock closure that returns the response
    $next = function ($req) use ($response) {
        return $response;
    };

    // Run the middleware
    $result = $this->middleware->handle($request, $next);

    // Assert the response is correct
    expect($result)->toBe($response);

    // Run the terminate method
    $this->middleware->terminate($request, $response);

    // Check that no event was fired for excluded paths
    Event::assertNotDispatched(CompleteApiLogItemEvent::class);

    // Check that no idempotent request was stored
    $apiLogItem = ApiLogItem::where('request_id', $this->idempotencyKey)->first();
    expect($apiLogItem)->toBeNull();
});

test('middleware handles error responses correctly', function () {
    // Create a mock request
    $request = Request::create('/api/test', 'POST', ['test_data' => 'invalid']);
    $request->headers->set('Idempotency-Key', $this->idempotencyKey);

    // Create a mock error response
    $response = new Response(
        json_encode(['message' => 'Validation failed']),
        422,
        ['Content-Type' => 'application/json']
    );

    // Create a mock closure that returns the response
    $next = function ($req) use ($response) {
        return $response;
    };

    // Run the middleware
    $result = $this->middleware->handle($request, $next);

    // Run the terminate method
    $this->middleware->terminate($request, $response);

    // Check that the event was fired with error response data
    Event::assertDispatched(CompleteApiLogItemEvent::class, function ($event) {
        return $event->requestId === $this->idempotencyKey
            && $event->apiLogData->statusCode === 422
            && $event->apiLogData->success === false;
    });

    // Check that the idempotent request was stored with error status
    $apiLogItem = ApiLogItem::where('request_id', $this->idempotencyKey)->first();
    expect($apiLogItem)->not->toBeNull()
        ->and($apiLogItem->response_status)->toBe(422)
        ->and($apiLogItem->is_error)->toBeTrue();
});
