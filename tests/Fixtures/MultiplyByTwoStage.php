<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Tests\Fixtures;

use Closure;
use PhilipRehberger\Pipeline\Contracts\Stage;

class MultiplyByTwoStage implements Stage
{
    public function handle(mixed $passable, Closure $next): mixed
    {
        return $next($passable * 2);
    }
}
