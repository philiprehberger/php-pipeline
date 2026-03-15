<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Tests\Fixtures;

use Closure;
use PhilipRehberger\Pipeline\Contracts\Stage;

class UpperCaseStage implements Stage
{
    public function handle(mixed $passable, Closure $next): mixed
    {
        return $next(strtoupper((string) $passable));
    }
}
