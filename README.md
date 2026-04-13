# PHP Pipeline

[![Tests](https://github.com/philiprehberger/php-pipeline/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-pipeline/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-pipeline.svg)](https://packagist.org/packages/philiprehberger/php-pipeline)
[![Last updated](https://img.shields.io/github/last-commit/philiprehberger/php-pipeline)](https://github.com/philiprehberger/php-pipeline/commits/main)

Composable pipeline pattern for processing data through ordered stages.

## Requirements

- PHP 8.2+

## Installation

```bash
composer require philiprehberger/php-pipeline
```

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

### Validation Checkpoints

Insert validation steps between stages to abort the pipeline on invalid data:

```php
$result = Pipeline::send(10)
    ->pipe(fn (mixed $value, \Closure $next) => $next($value * 2))
    ->checkpoint(fn (mixed $value) => $value <= 100)
    ->pipe(fn (mixed $value, \Closure $next) => $next($value + 1))
    ->process();

// 21
```

If the checkpoint returns false or throws, a `CheckpointFailedException` is thrown.

### Stage Profiling

Profile stage execution time and memory usage:

```php
use PhilipRehberger\Pipeline\Pipeline;

$result = Pipeline::send('hello')
    ->pipe(UpperCaseStage::class)
    ->pipe(AppendSuffixStage::class)
    ->processWithProfile();

$result->value();         // "HELLO_suffix"
$result->stages();        // [{name, duration_ms, memory_delta}, ...]
$result->totalDuration(); // Total ms across all stages
$result->slowestStage();  // Name of the slowest stage
```

### Pipeline Templates

Register reusable pipeline configurations:

```php
use PhilipRehberger\Pipeline\Pipeline;

Pipeline::register('text-cleanup', function (PendingPipeline $p) {
    $p->pipe(TrimStage::class)
      ->pipe(UpperCaseStage::class);
});

$result = Pipeline::fromTemplate('text-cleanup')
    ->send('  hello  ')
    ->thenReturn();

// "HELLO"
```

### Typed Exception Handling

Catch specific exception types and recover gracefully:

```php
$result = Pipeline::send($data)
    ->through([RiskyStage::class])
    ->catchException(ValidationException::class, function (\Throwable $e, mixed $passable) {
        return $passable; // Recover from validation errors only
    })
    ->thenReturn();

// Non-matching exceptions propagate normally
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

## API

| Method | Description |
|--------|-------------|
| `Pipeline::send(mixed $passable)` | Create a new pipeline with the given data |
| `Pipeline::register(string $name, callable $builder)` | Register a reusable pipeline template |
| `Pipeline::fromTemplate(string $name)` | Create a pipeline from a registered template |
| `Pipeline::hasTemplate(string $name)` | Check if a template is registered |
| `->through(array $stages)` | Set the array of stages |
| `->pipe(string\|callable $stage)` | Append a single stage |
| `->when(bool $condition, string\|callable $stage)` | Add stage if condition is true |
| `->unless(bool $condition, string\|callable $stage)` | Add stage if condition is false |
| `->withContext(PipelineContext $context)` | Attach shared context passed to stages |
| `->tap(callable $fn)` | Add a side-effect stage that does not modify the payload |
| `->checkpoint(callable $validator)` | Insert a validation checkpoint between stages |
| `->profile()` | Enable profiling mode |
| `->catchException(string $class, callable $handler)` | Register a handler for a specific exception type |
| `->onFailure(callable $handler)` | Register a failure handler |
| `->process()` | Execute the pipeline and return the result |
| `->processWithProfile()` | Execute and return a `ProfiledResult` with timing data |
| `->thenReturn()` | Alias for `process()` |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

## Support

If you find this project useful:

⭐ [Star the repo](https://github.com/philiprehberger/php-pipeline)

🐛 [Report issues](https://github.com/philiprehberger/php-pipeline/issues?q=is%3Aissue+is%3Aopen+label%3Abug)

💡 [Suggest features](https://github.com/philiprehberger/php-pipeline/issues?q=is%3Aissue+is%3Aopen+label%3Aenhancement)

❤️ [Sponsor development](https://github.com/sponsors/philiprehberger)

🌐 [All Open Source Projects](https://philiprehberger.com/open-source-packages)

💻 [GitHub Profile](https://github.com/philiprehberger)

🔗 [LinkedIn Profile](https://www.linkedin.com/in/philiprehberger)

## License

[MIT](LICENSE)
