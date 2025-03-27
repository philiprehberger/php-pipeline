<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Exceptions;

/**
 * Exception thrown when a pipeline checkpoint validation fails.
 */
class CheckpointFailedException extends PipelineException
{
    public function __construct(string $message = 'Pipeline checkpoint validation failed.')
    {
        parent::__construct(
            stageName: 'checkpoint',
            message: $message,
        );
    }
}
