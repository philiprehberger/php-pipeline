# PHP Pipeline

[![Tests](https://github.com/philiprehberger/php-pipeline/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-pipeline/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-pipeline.svg)](https://packagist.org/packages/philiprehberger/php-pipeline)
[![License](https://img.shields.io/github/license/philiprehberger/php-pipeline)](LICENSE)

Composable pipeline pattern for processing data through ordered stages.

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP        | ^8.2    |

---

## Installation

```bash
composer require philiprehberger/php-pipeline
```

---

## Usage

### Basic Pipeline

```php
use PhilipRehberger\Pipeline\Pipeline;

$result = Pipeline::send('hello world')
    ->through([
        fn (string $value, \Closure $next) => $next(strtoupper($value)),
        fn (string $value, \Closure $next) => $next(str_replace(' ', '-', $value)),
    ])
    ->thenReturn();

// "HELLO-WORLD"
```

### Class-Based Stages

Implement the `Stage` contract for reusable, testable stages:

```php
use PhilipRehberger\Pipeline\Contracts\Stage;
use Closure;

class TrimStage implements Stage
{
    public function handle(mixed $passable, Closure $next): mixed
    {
        return $next(trim($passable));
    }
}

$result = Pipeline::send('  hello  ')
    ->pipe(TrimStage::class)
    ->thenReturn();

// "hello"
```

### Conditional Stages

```php
$isAdmin = true;

$result = Pipeline::send($data)
    ->pipe(ValidateStage::class)
    ->when($isAdmin, AdminEnrichStage::class)
    ->unless($isAdmin, GuestFilterStage::class)
    ->process();
```

### Pipeline Context

Share state between stages using `PipelineContext`:

```php
use PhilipRehberger\Pipeline\Pipeline;
use PhilipRehberger\Pipeline\PipelineContext;

$context = new PipelineContext();

$result = Pipeline::send($data)
    ->withContext($context)
    ->through([
        function (mixed $value, \Closure $next, PipelineContext $ctx) {
            $ctx->set('started_at', microtime(true));
            return $next($value);
        },
        function (mixed $value, \Closure $next, PipelineContext $ctx) {
            // Access values set by earlier stages
            $started = $ctx->get('started_at');
            return $next($value);
        },
    ])
    ->thenReturn();

// Read context after pipeline completes
$context->all();
```

### Tap

Add side-effect stages that observe the payload without modifying it:

```php
$result = Pipeline::send('hello')
    ->pipe(fn (string $value, \Closure $next) => $next(strtoupper($value)))
    ->tap(function (string $value) {
        logger()->info('After uppercase: ' . $value);
    })
    ->thenReturn();

// "HELLO" — tap does not change the payload
```

### Error Handling

```php
$result = Pipeline::send($data)
    ->through([RiskyStage::class])
    ->onFailure(function (\Throwable $e, mixed $passable) {
        return $passable; // Return original data on failure
    })
    ->process();
```

---

## API

| Method | Description |
|--------|-------------|
| `Pipeline::send(mixed $passable)` | Create a new pipeline with the given data |
| `->through(array $stages)` | Set the array of stages |
| `->pipe(string\|callable $stage)` | Append a single stage |
| `->when(bool $condition, string\|callable $stage)` | Add stage if condition is true |
| `->unless(bool $condition, string\|callable $stage)` | Add stage if condition is false |
| `->withContext(PipelineContext $context)` | Attach shared context passed to stages |
| `->tap(callable $fn)` | Add a side-effect stage that does not modify the payload |
| `->onFailure(callable $handler)` | Register a failure handler |
| `->process()` | Execute the pipeline and return the result |
| `->thenReturn()` | Alias for `process()` |

---

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
