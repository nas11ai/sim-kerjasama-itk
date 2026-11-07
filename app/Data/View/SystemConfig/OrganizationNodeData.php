<?php

namespace App\Data\View\SystemConfig;

use Spatie\LaravelData\Data;

/** Satu baris pohon ADMIN-08-02, berurutan depth-first. */
final class OrganizationNodeData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $typeLabel,
        public bool $isActive,
        public ?int $parentId,
        public int $depth,
        public bool $hasChildren,
    ) {}
}
