<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Prahsys\ApiLogs\ApiLogPipelineManager;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Redactors\CommonBodyFields;
use Prahsys\ApiLogs\Redactors\CommonHeaderFields;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear the singleton instance to ensure fresh instance for each test
    $this->app->forgetInstance(ApiLogPipelineManager::class);
});

it('processes full pipeline and redacts sensitive data', function () {
    $loggedData = null;

    Log::shouldReceive('channel')
        ->with('test_redacted')
        ->andReturnSelf()
        ->shouldReceive('info')
        ->with('POST /api/login', Mockery::on(function ($data) use (&$loggedData) {
            $loggedData = $data;

            return true;
        }))
        ->once();

    $manager = app(ApiLogPipelineManager::class);
    $manager->clearChannels(); // Clear any existing channels from singleton
    $manager->addChannel('test_redacted', [
        CommonHeaderFields::class,
        CommonBodyFields::class,
    ]);

    $dto = ApiLogData::instance([
        'id' => 'test-123',
        'operationName' => 'loginUser',
        'url' => '/api/login',
        'success' => true,
        'statusCode' => 200,
        'method' => 'POST',
        'request' => [
            'body' => [
                'username' => 'john',
                'password' => 'supersecret123',
                'remember_me' => true,
            ],
            'headers' => [
                'authorization' => 'Bearer token123',
                'content-type' => 'application/json',
                'user-agent' => 'TestClient/1.0',
            ],
        ],
        'response' => [
            'body' => [
                'user_id' => 123,
                'access_token' => 'newtoken456',
                'username' => 'john',
            ],
            'headers' => [
                'content-type' => 'application/json',
                'set-cookie' => 'session=abc123; HttpOnly',
            ],
        ],
    ]);

    $manager->processAndLog($dto);

    // Verify sensitive data was redacted
    expect($loggedData['request']['body']['password'])->toBe('[REDACTED]');
    expect($loggedData['request']['headers']['authorization'])->toBe('[REDACTED]');
    expect($loggedData['response']['body']['access_token'])->toBe('[REDACTED]');
    expect($loggedData['response']['headers']['set-cookie'])->toBe('[REDACTED]');

    // Verify non-sensitive data was preserved
    expect($loggedData['request']['body']['username'])->toBe('john');
    expect($loggedData['request']['body']['remember_me'])->toBeTrue();
    expect($loggedData['request']['headers']['content-type'])->toBe('application/json');
    expect($loggedData['request']['headers']['user-agent'])->toBe('TestClient/1.0');
    expect($loggedData['response']['body']['user_id'])->toBe(123);
    expect($loggedData['response']['body']['username'])->toBe('john');
});

it('preserves all data in raw pipeline', function () {
    $loggedData = null;

    Log::shouldReceive('channel')
        ->with('test_raw')
        ->andReturnSelf()
        ->shouldReceive('info')
        ->with('GET ', Mockery::on(function ($data) use (&$loggedData) {
            $loggedData = $data;

            return true;
        }))
        ->once();

    $manager = app(ApiLogPipelineManager::class);
    $manager->clearChannels(); // Clear any existing channels from singleton
    $manager->addChannel('test_raw', []); // No redactors

    $dto = ApiLogData::instance([
        'id' => 'test-123',
        'request' => [
            'body' => ['password' => 'secret123'],
            'headers' => ['authorization' => 'Bearer token456'],
        ],
    ]);

    $manager->processAndLog($dto);

    // Verify all data was preserved
    expect($loggedData['request']['body']['password'])->toBe('secret123');
    expect($loggedData['request']['headers']['authorization'])->toBe('Bearer token456');
});
