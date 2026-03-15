<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline;

/**
 * Static entry point for creating pipelines.
 */
class Pipeline
{
    /**
     * Create a new pending pipeline with the given passable data.
     */
    public static function send(mixed $passable): PendingPipeline
    {
        return new PendingPipeline($passable);
    }
}
