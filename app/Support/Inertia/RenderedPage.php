<?php

namespace App\Support\Inertia;

/**
 * Satu pemanggilan `Inertia::render()` / `inertia()` di sebuah controller.
 */
final class RenderedPage
{
    public function __construct(
        public readonly string $page,
        public readonly string $file,
        public readonly int $line,
    ) {}

    public function location(): string
    {
        return $this->file.':'.$this->line;
    }
}
