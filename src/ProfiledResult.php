<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline;

/**
 * Value object containing a pipeline result with per-stage profiling data.
 */
class ProfiledResult
{
    /**
     * @param  array<int, array{name: string, duration_ms: float, memory_delta: int}>  $stages
     */
    public function __construct(
        private readonly mixed $result,
        private readonly array $stages,
    ) {}

    /**
     * Get the pipeline result value.
     */
    public function value(): mixed
    {
        return $this->result;
    }

    /**
     * Get the array of stage profile entries.
     *
     * @return array<int, array{name: string, duration_ms: float, memory_delta: int}>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    /**
     * Get the total duration of all stages in milliseconds.
     */
    public function totalDuration(): float
    {
        return array_sum(array_column($this->stages, 'duration_ms'));
    }

    /**
     * Get the name of the slowest stage.
     */
    public function slowestStage(): string
    {
        if ($this->stages === []) {
            return '';
        }

        $slowest = $this->stages[0];

        foreach ($this->stages as $stage) {
            if ($stage['duration_ms'] > $slowest['duration_ms']) {
                $slowest = $stage;
            }
        }

        return $slowest['name'];
    }
}
