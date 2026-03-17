# Changelog

All notable changes to `php-pipeline` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.1.0] - 2026-03-16

### Added
- `PipelineContext` class for sharing state between pipeline stages
- `withContext(PipelineContext $context)` method on `PendingPipeline` to attach shared context
- `tap(callable $fn)` method on `PendingPipeline` for side-effect stages that pass the payload through unchanged

## [1.0.2] - 2026-03-16

### Changed
- Standardize composer.json: add type, homepage, scripts

## [1.0.1] - 2026-03-15

### Changed
- Standardize README badges

## [1.0.0] - 2026-03-15

### Added
- Initial release
- Static entry point `Pipeline::send()` for creating pipelines
- Fluent builder with `through()`, `pipe()`, `when()`, `unless()` methods
- `Stage` contract interface for class-based stages
- Callable stage support for inline processing
- `onFailure()` handler for custom error handling
- `PipelineException` with stage name context
- `process()` and `thenReturn()` execution methods
