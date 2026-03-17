<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline;

use Closure;
use PhilipRehberger\Pipeline\Contracts\Stage;
use PhilipRehberger\Pipeline\Exceptions\PipelineException;
use Throwable;

/**
 * Fluent builder for constructing and executing a pipeline.
 */
class PendingPipeline
{
    /** @var array<int, string|callable> */
    private array $stages = [];

    private ?Closure $failureHandler = null;

    private ?PipelineContext $context = null;

    /**
     * Create a new pending pipeline instance.
     */
    public function __construct(
        private readonly mixed $passable,
    ) {}

    /**
     * Set the array of stages for the pipeline.
     *
     * @param  array<int, string|callable>  $stages
     */
    public function through(array $stages): self
    {
        $this->stages = $stages;

        return $this;
    }

    /**
     * Add a single stage to the pipeline.
     */
    public function pipe(string|callable $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    /**
     * Conditionally add a stage when the condition is true.
     */
    public function when(bool $condition, string|callable $stage): self
    {
        if ($condition) {
            $this->stages[] = $stage;
        }

        return $this;
    }

    /**
     * Conditionally add a stage when the condition is false.
     */
    public function unless(bool $condition, string|callable $stage): self
    {
        if (! $condition) {
            $this->stages[] = $stage;
        }

        return $this;
    }

    /**
     * Attach a shared context to the pipeline.
     *
     * When a context is set, each stage receives the payload and the context
     * as arguments: `($passable, $context)` for closures, or via the second
     * parameter of `Stage::handle()`.
     */
    public function withContext(PipelineContext $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add a tap stage that performs a side effect without modifying the payload.
     *
     * The callable receives the current payload (and context, if set) but its
     * return value is ignored — the original payload is passed to the next stage.
     */
    public function tap(callable $fn): static
    {
        $this->stages[] = function (mixed $passable, Closure $next) use ($fn): mixed {
            if ($this->context !== null) {
                $fn($passable, $this->context);
            } else {
                $fn($passable);
            }

            return $next($passable);
        };

        return $this;
    }

    /**
     * Register a failure handler for the pipeline.
     */
    public function onFailure(callable $handler): self
    {
        $this->failureHandler = $handler(...);

        return $this;
    }

    /**
     * Process the passable through all stages and return the result.
     */
    public function process(): mixed
    {
        return $this->execute();
    }

    /**
     * Alias for process — run the pipeline and return the result.
     */
    public function thenReturn(): mixed
    {
        return $this->execute();
    }

    /**
     * Build and execute the pipeline.
     */
    private function execute(): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->stages),
            function (Closure $next, string|callable $stage): Closure {
                return function (mixed $passable) use ($stage, $next): mixed {
                    return $this->executeStage($stage, $passable, $next);
                };
            },
            fn (mixed $passable): mixed => $passable,
        );

        return $pipeline($this->passable);
    }

    /**
     * Execute a single stage within the pipeline.
     */
    private function executeStage(string|callable $stage, mixed $passable, Closure $next): mixed
    {
        $stageName = is_string($stage) ? $stage : 'Closure';

        try {
            if (is_string($stage)) {
                /** @var Stage $instance */
                $instance = new $stage;

                if ($this->context !== null) {
                    return $instance->handle($passable, $next, $this->context);
                }

                return $instance->handle($passable, $next);
            }

            if ($this->context !== null) {
                return $stage($passable, $next, $this->context);
            }

            return $stage($passable, $next);
        } catch (PipelineException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->failureHandler !== null) {
                return ($this->failureHandler)($e, $passable);
            }

            throw PipelineException::fromStage($stageName, $e);
        }
    }
}
