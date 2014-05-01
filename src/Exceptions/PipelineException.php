<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a pipeline stage fails.
 */
class PipelineException extends RuntimeException
{
    /**
     * Create a new pipeline exception.
     */
    public function __construct(
        public readonly string $stageName,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $resolvedMessage = $message ?: "Pipeline failed at stage [{$stageName}].";

        parent::__construct($resolvedMessage, $code, $previous);
    }

    /**
     * Create an exception from a caught throwable within a stage.
     */
    public static function fromStage(string $stageName, Throwable $exception): self
    {
        return new self(
            stageName: $stageName,
            message: "Pipeline failed at stage [{$stageName}]: {$exception->getMessage()}",
            code: (int) $exception->getCode(),
            previous: $exception,
        );
    }
}
