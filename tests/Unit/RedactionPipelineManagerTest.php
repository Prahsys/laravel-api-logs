<?php

use Illuminate\Container\Container;
use Illuminate\Pipeline\Pipeline;
use Prahsys\ApiLogs\ApiLogPipelineManager;
use Prahsys\ApiLogs\Redactors\DotNotationRedactor;

beforeEach(function () {
    // Mock Laravel container and Pipeline
    $container = new Container;
    $container->bind(Pipeline::class, function () use ($container) {
        return new Pipeline($container);
    });
    Container::setInstance($container);

    // Clear the singleton instance to ensure fresh instance for each test
    app()->forgetInstance(ApiLogPipelineManager::class);
});

afterEach(function () {
    Mockery::close();
});

it('loads channels from config', function () {
    $manager = app(ApiLogPipelineManager::class);

    $config = [
        'test_raw' => [],
        'test_redacted' => [[DotNotationRedactor::class, ['test.path']]],
    ];

    $manager->loadChannels($config);

    expect($manager->getChannels())->toBe($config);
});

it('adds single channel', function () {
    $manager = app(ApiLogPipelineManager::class);

    $manager->addChannel('test_channel', [[DotNotationRedactor::class, ['test.path']]]);

    $channels = $manager->getChannels();
    expect($channels)->toHaveKey('test_channel');
    expect($channels['test_channel'])->toBe([[DotNotationRedactor::class, ['test.path']]]);
});

it('resolves array redactor config', function () {
    $manager = app(ApiLogPipelineManager::class);

    // Use reflection to test the protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('resolveRedactors');
    $method->setAccessible(true);

    $config = [
        [DotNotationRedactor::class, ['request.body.password'], '[REDACTED]'],
    ];

    $redactors = $method->invoke($manager, $config);

    expect($redactors)->toHaveCount(1);
    expect($redactors[0])->toBeInstanceOf(DotNotationRedactor::class);
});

it('clears channels', function () {
    $manager = app(ApiLogPipelineManager::class);
    $manager->addChannel('test', []);

    expect($manager->getChannels())->not->toBeEmpty();

    $manager->clearChannels();

    expect($manager->getChannels())->toBeEmpty();
});
