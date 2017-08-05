<?php

declare(strict_types=1);

namespace PhilipRehberger\Pipeline;

/**
 * Shared context for passing state between pipeline stages.
 */
class PipelineContext
{
    /** @var array<string, mixed> */
    private array $items = [];

    /**
     * Set a value in the context.
     */
    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /**
     * Get a value from the context, or a default if not present.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Determine if the context contains the given key.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get all items from the context.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
