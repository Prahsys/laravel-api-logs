<?php

namespace Prahsys\ApiLogs;

use Illuminate\Support\Facades\Log;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Processors\ApiLogProcessor;

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
     * Register processors on log channels.
     */
    public function registerProcessors(): void
    {
        foreach ($this->channels as $channelName => $redactors) {
            $processor = new ApiLogProcessor($redactors);
            Log::channel($channelName)->getLogger()->pushProcessor($processor);
        }
    }

    /**
     * Log an ApiLogData through all configured channels.
     */
    public function log(ApiLogData $data): void
    {
        $context = $data->toArray();

        foreach ($this->channels as $channelName => $redactors) {
            Log::channel($channelName)->info($data->method.' '.$data->url, $context);
        }
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
