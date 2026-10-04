<?php

namespace App\Data\Request;

/** Parameter mentah daftar tahap formulir; pembatasannya dilakukan Query, bukan DTO ini. */
final class FormPhaseListInput
{
    public function __construct(
        public readonly string $search,
        public readonly string $active,
        public readonly string $sortBy,
        public readonly string $sortOrder,
        public readonly int $perPage,
    ) {}
}
