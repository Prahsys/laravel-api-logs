<?php

namespace Prahsys\ApiLogs\Pipelines;

use Prahsys\ApiLogs\Contracts\RedactorInterface;

class RedactionPipeline
{
    /**
     * The redactors in the pipeline.
     *
     * @var array<RedactorInterface>
     */
    protected array $redactors = [];

    /**
     * The log channel this pipeline writes to.
     */
    protected string $channel;

    /**
     * Create a new redaction pipeline instance.
     *
     * @param  array<RedactorInterface>  $redactors
     */
    public function __construct(array $redactors = [], string $channel = 'api_logs')
    {
        $this->redactors = $redactors;
        $this->channel = $channel;
    }

    /**
     * Add a redactor to the pipeline.
     *
     * @return $this
     */
    public function pipe(RedactorInterface $redactor): self
    {
        $this->redactors[] = $redactor;

        return $this;
    }

    /**
     * Add multiple redactors to the pipeline.
     *
     * @param  array<RedactorInterface>  $redactors
     * @return $this
     */
    public function pipes(array $redactors): self
    {
        foreach ($redactors as $redactor) {
            $this->pipe($redactor);
        }

        return $this;
    }

    /**
     * Process the data through the pipeline.
     */
    public function process(mixed $data): mixed
    {
        return array_reduce(
            $this->redactors,
            fn (mixed $carry, RedactorInterface $redactor) => $redactor->redact($carry),
            $data
        );
    }

    /**
     * Get all redactors in the pipeline.
     *
     * @return array<RedactorInterface>
     */
    public function getRedactors(): array
    {
        return $this->redactors;
    }

    /**
     * Clear all redactors from the pipeline.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->redactors = [];

        return $this;
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
