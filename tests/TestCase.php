<?php

namespace Prahsys\ApiLogs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Prahsys\ApiLogs\ApiLogsServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ApiLogsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure spatie/laravel-data
        $app['config']->set('data.validation_strategy', 'always');
        $app['config']->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
        ]);
        $app['config']->set('data.transformers', []);
        $app['config']->set('data.casts', []);
        $app['config']->set('data.rule_inferrers', []);
        $app['config']->set('data.max_transformation_depth', 512);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', true);

        // Configure the API logs channels
        $app['config']->set('api-logs.channels', [
            'test_raw' => [],
            'test_redacted' => [
                \Prahsys\ApiLogs\Redactors\CommonHeaderFieldsRedactor::class,
                \Prahsys\ApiLogs\Redactors\CommonBodyFieldsRedactor::class,
            ],
        ]);

        // Configure log channels for testing
        $app['config']->set('logging.channels.test_raw', [
            'driver' => 'array',
        ]);
        $app['config']->set('logging.channels.test_redacted', [
            'driver' => 'array',
        ]);
    }
}
