# PHP Pipeline

[![Tests](https://github.com/philiprehberger/php-pipeline/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-pipeline/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-pipeline.svg)](https://packagist.org/packages/philiprehberger/php-pipeline)
[![Total Downloads](https://img.shields.io/packagist/dt/philiprehberger/php-pipeline.svg)](https://packagist.org/packages/philiprehberger/php-pipeline)
[![PHP Version Require](https://img.shields.io/packagist/php-v/philiprehberger/php-pipeline.svg)](https://packagist.org/packages/philiprehberger/php-pipeline)
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
| `->onFailure(callable $handler)` | Register a failure handler |
| `->process()` | Execute the pipeline and return the result |
| `->thenReturn()` | Alias for `process()` |

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Code style:

```bash
vendor/bin/pint
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

---

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
