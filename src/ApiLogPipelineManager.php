<?php

namespace Prahsys\ApiLogs;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use Prahsys\ApiLogs\Data\ApiLogData;

class ApiLogPipelineManager
{
    protected array $channels = [];

    protected static ?self $instance = null;

    /**
     * Load channels from configuration.
     *
     * @param  array  $channelConfig  ['channel_name' => [redactors...], ...]
     * @return $this
     */
    public function loadChannels(array $channelConfig): self
    {
        $this->channels = $channelConfig;

        return $this;
    }

    /**
     * Add a single channel with redactors.
     *
     * @return $this
     */
    public function addChannel(string $channelName, array $redactors = []): self
    {
        $this->channels[$channelName] = $redactors;

        return $this;
    }

    /**
     * Process and log an ApiLogData through all configured channels.
     */
    public function processAndLog(ApiLogData $data): void
    {
        foreach ($this->channels as $channelName => $redactors) {
            $processedData = $this->processData($data->toArray(), $redactors);
            Log::channel($channelName)->info($data->method.' '.$data->url, $processedData);
        }
    }

    /**
     * Process data through a pipeline of redactors.
     */
    protected function processData(array $data, array $redactorConfigs): array
    {
        $redactors = $this->resolveRedactors($redactorConfigs);

        return app(Pipeline::class)
            ->send($data)
            ->through($redactors)
            ->thenReturn();
    }

    /**
     * Resolve redactor configurations into instances.
     */
    protected function resolveRedactors(array $redactorConfigs): array
    {
        return array_map(function ($config) {
            if (is_string($config)) {
                // Simple class name - need to instantiate with default args
                return new $config;
            }

            if (is_array($config)) {
                // [class, ...args] format
                $class = array_shift($config);

                return new $class(...$config);
            }

            if (is_object($config)) {
                // Already instantiated
                return $config;
            }

            throw new \InvalidArgumentException('Invalid redactor configuration');
        }, $redactorConfigs);
    }

    /**
     * Get all configured channels.
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Clear all configured channels.
     *
     * @return $this
     */
    public function clearChannels(): self
    {
        $this->channels = [];

        return $this;
    }
}
