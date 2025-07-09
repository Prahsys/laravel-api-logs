<?php

namespace Prahsys\ApiLogs\Pipelines;

use Prahsys\ApiLogs\Contracts\RedactorInterface;

class RedactedPipeline extends RedactionPipeline
{
    /**
     * The log channel this pipeline writes to.
     */
    protected string $channel = 'api_logs_redacted';

    /**
     * Create a new redacted pipeline.
     *
     * @param  array<RedactorInterface>  $redactors
     */
    public function __construct(array $redactors = [], ?string $channel = null)
    {
        parent::__construct($redactors);

        if ($channel) {
            $this->channel = $channel;
        }
    }

    /**
     * Get the log channel for this pipeline.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Set the log channel for this pipeline.
     */
    public function setChannel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }
}
