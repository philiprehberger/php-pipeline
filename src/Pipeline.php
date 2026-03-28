<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline;

/**
 * Static entry point for creating pipelines.
 */
class Pipeline
{
    /** @var array<string, callable> */
    private static array $templates = [];

    /**
     * Create a new pending pipeline with the given passable data.
     */
    public static function send(mixed $passable): PendingPipeline
    {
        return new PendingPipeline($passable);
    }

    /**
     * Register a reusable pipeline template.
     *
     * The builder callable receives a PendingPipeline and should configure it.
     */
    public static function register(string $name, callable $builder): void
    {
        self::$templates[$name] = $builder;
    }

    /**
     * Create a new PendingPipeline from a registered template.
     *
     * @throws Exceptions\PipelineException If the template is not registered.
     */
    public static function fromTemplate(string $name): PendingPipeline
    {
        if (! isset(self::$templates[$name])) {
            throw new Exceptions\PipelineException(
                stageName: 'template',
                message: "Pipeline template [{$name}] is not registered.",
            );
        }

        $pipeline = new PendingPipeline(null);
        (self::$templates[$name])($pipeline);

        return $pipeline;
    }

    /**
     * Check if a template with the given name is registered.
     */
    public static function hasTemplate(string $name): bool
    {
        return isset(self::$templates[$name]);
    }

    /**
     * Clear all registered templates (useful for testing).
     */
    public static function clearTemplates(): void
    {
        self::$templates = [];
    }
}
