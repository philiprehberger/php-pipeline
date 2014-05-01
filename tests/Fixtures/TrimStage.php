<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Tests\Fixtures;

use Closure;
use PhilipRehberger\Pipeline\Contracts\Stage;

class TrimStage implements Stage
{
    public function handle(mixed $passable, Closure $next): mixed
    {
        return $next(trim((string) $passable));
    }
}
