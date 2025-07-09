<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Events\CompleteIdempotentRequestEvent;
use Prahsys\ApiLogs\Http\Middleware\IdempotencyLogMiddleware;
use Prahsys\ApiLogs\Services\IdempotencyService;
use Prahsys\ApiLogs\Services\ModelIdempotencyTracker;

beforeEach(function () {
    $this->idempotencyService = Mockery::mock(IdempotencyService::class);
    $this->middleware = new IdempotencyLogMiddleware(
        $this->idempotencyService
    );
    $this->requestId = (string) Str::uuid();
});

test('startApiLogData creates ApiLogData with correct request data', function () {
    $request = Request::create('/api/users', 'POST', ['name' => 'John']);
    $request->headers->set('Accept-Version', '2.0');
    $request->headers->set('User-Agent', 'TestAgent');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('startApiLogData');
    $method->setAccessible(true);

    $apiLogData = $method->invoke($this->middleware, $request, $this->requestId);

    expect($apiLogData)->toBeInstanceOf(ApiLogData::class)
        ->and($apiLogData->id)->toBe($this->requestId)
        ->and($apiLogData->method)->toBe('POST')
        ->and($apiLogData->url)->toBe('http://localhost/api/users')
        ->and($apiLogData->operationName)->toBe('POST api/users')
        ->and($apiLogData->request['headers'])->toHaveKey('accept-version')
        ->and($apiLogData->request['body'])->toBe(['name' => 'John'])
        ->and($apiLogData->request['api_version'])->toBe('2.0')
        ->and($apiLogData->request)->toHaveKey('timestamp');
});

test('completeApiLogData adds response data correctly', function () {
    $apiLogData = ApiLogData::instance(['id' => $this->requestId]);
    $response = new Response(
        json_encode(['success' => true]),
        200,
        ['Content-Type' => 'application/json']
    );

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('completeApiLogData');
    $method->setAccessible(true);

    $method->invoke($this->middleware, $apiLogData, $response);

    expect($apiLogData->statusCode)->toBe(200)
        ->and($apiLogData->success)->toBeTrue()
        ->and($apiLogData->response['headers'])->toHaveKey('content-type')
        ->and($apiLogData->response['body'])->toBe(['success' => true])
        ->and($apiLogData->response)->toHaveKey('timestamp');
});

test('completeApiLogData handles error responses correctly', function () {
    $apiLogData = ApiLogData::instance(['id' => $this->requestId]);
    $response = new Response(
        json_encode(['error' => 'Not found']),
        404,
        ['Content-Type' => 'application/json']
    );

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('completeApiLogData');
    $method->setAccessible(true);

    $method->invoke($this->middleware, $apiLogData, $response);

    expect($apiLogData->statusCode)->toBe(404)
        ->and($apiLogData->success)->toBeFalse()
        ->and($apiLogData->response['body'])->toBe(['error' => 'Not found']);
});

test('getOperationName returns route name when available', function () {
    $request = Request::create('/api/users', 'GET');

    // Mock route with name
    $route = Mockery::mock();
    $route->shouldReceive('getName')->andReturn('users.index');
    $request->setRouteResolver(function () use ($route) {
        return $route;
    });

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('getOperationName');
    $method->setAccessible(true);

    $operationName = $method->invoke($this->middleware, $request);

    expect($operationName)->toBe('users.index');
});

test('getOperationName falls back to method and path', function () {
    $request = Request::create('/api/users', 'GET');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('getOperationName');
    $method->setAccessible(true);

    $operationName = $method->invoke($this->middleware, $request);

    expect($operationName)->toBe('GET api/users');
});

test('getResponseBody handles JSON content correctly', function () {
    $response = new Response(
        json_encode(['data' => 'test']),
        200,
        ['Content-Type' => 'application/json']
    );

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('getResponseBody');
    $method->setAccessible(true);

    $body = $method->invoke($this->middleware, $response);

    expect($body)->toBe(['data' => 'test']);
});

test('getResponseBody handles non-JSON content correctly', function () {
    $response = new Response('Plain text content', 200, ['Content-Type' => 'text/plain']);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('getResponseBody');
    $method->setAccessible(true);

    $body = $method->invoke($this->middleware, $response);

    expect($body)->toBe('Plain text content');
});

test('shouldLogRequest returns false for OPTIONS requests', function () {
    $request = Request::create('/api/test', 'OPTIONS');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('shouldLogRequest');
    $method->setAccessible(true);

    $shouldLog = $method->invoke($this->middleware, $request);

    expect($shouldLog)->toBeFalse();
});

test('shouldLogRequest returns false for excluded paths', function () {
    config(['prahsys-api-logs.exclude_paths' => ['api/docs', 'api/health']]);

    $request = Request::create('/api/docs', 'GET');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('shouldLogRequest');
    $method->setAccessible(true);

    $shouldLog = $method->invoke($this->middleware, $request);

    expect($shouldLog)->toBeFalse();
});

test('shouldLogRequest returns true for valid requests', function () {
    $request = Request::create('/api/users', 'GET');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('shouldLogRequest');
    $method->setAccessible(true);

    $shouldLog = $method->invoke($this->middleware, $request);

    expect($shouldLog)->toBeTrue();
});

test('storeIdempotentRequest creates database record correctly', function () {
    $apiLogData = ApiLogData::instance([
        'id' => $this->requestId,
        'operationName' => 'test.create',
        'url' => 'http://localhost/api/test',
        'success' => true,
        'statusCode' => 200,
        'method' => 'POST',
        'request' => ['api_version' => '2.0', 'timestamp' => now()->toIso8601String()],
        'response' => ['timestamp' => now()->toIso8601String()],
    ]);

    $expectedLogData = [
        'request_id' => $this->requestId,
        'path' => '/api/test',
        'method' => 'POST',
        'api_version' => '2.0',
        'request_at' => $apiLogData->request['timestamp'],
        'response_at' => $apiLogData->response['timestamp'],
        'response_status' => 200,
        'is_error' => false,
    ];

    $mockIdempotentRequest = Mockery::mock();
    $this->idempotencyService->shouldReceive('storeIdempotentRequest')
        ->once()
        ->with($expectedLogData)
        ->andReturn($mockIdempotentRequest);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('storeIdempotentRequest');
    $method->setAccessible(true);

    $result = $method->invoke($this->middleware, $apiLogData);

    expect($result)->toBe($mockIdempotentRequest);
});

test('fireCompleteEvent fires event with tracked models and ApiLogData', function () {
    Event::fake();

    $apiLogData = ApiLogData::instance(['id' => $this->requestId]);

    $mockIdempotentRequest = Mockery::mock();
    $mockIdempotentRequest->id = 'idempotent-request-id';

    // Mock the ModelIdempotencyTracker
    $mockTracker = Mockery::mock(ModelIdempotencyTracker::class);
    $mockModels = collect([
        ['model_class' => 'App\Models\User', 'model_id' => 1],
        ['model_class' => 'App\Models\Order', 'model_id' => 2],
    ]);

    $mockTracker->shouldReceive('getModelsForRequest')
        ->once()
        ->with($this->requestId)
        ->andReturn($mockModels);

    $mockTracker->shouldReceive('clearRequest')
        ->once()
        ->with($this->requestId);

    // Mock the app() call to return our mock tracker
    $this->app->instance(ModelIdempotencyTracker::class, $mockTracker);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('fireCompleteEvent');
    $method->setAccessible(true);

    $method->invoke($this->middleware, $apiLogData, $mockIdempotentRequest);

    // Assert the event was fired with correct parameters
    Event::assertDispatched(CompleteIdempotentRequestEvent::class, function ($event) use ($apiLogData) {
        return $event->requestId === $this->requestId
            && $event->idempotentRequestId === 'idempotent-request-id'
            && count($event->models) === 2
            && $event->apiLogData === $apiLogData;
    });
});

test('fireCompleteEvent does not fire when no idempotent request', function () {
    Event::fake();

    $apiLogData = ApiLogData::instance(['id' => $this->requestId]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('fireCompleteEvent');
    $method->setAccessible(true);

    $method->invoke($this->middleware, $apiLogData, null);

    // Assert no event was fired
    Event::assertNotDispatched(CompleteIdempotentRequestEvent::class);
});
