<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Contracts;

use Closure;

/**
 * Contract for pipeline stages.
 */
interface Stage
{
    /**
     * Handle the passable data and pass it to the next stage.
     */
    public function handle(mixed $passable, Closure $next): mixed;
}
