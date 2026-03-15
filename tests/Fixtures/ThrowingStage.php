<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline\Tests\Fixtures;

use Closure;
use PhilipRehberger\Pipeline\Contracts\Stage;
use RuntimeException;

class ThrowingStage implements Stage
{
    public function handle(mixed $passable, Closure $next): mixed
    {
        throw new RuntimeException('Stage failed intentionally.');
    }
}
