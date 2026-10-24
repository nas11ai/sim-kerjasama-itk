<?php

namespace App\Data\Request;

/** Parameter mentah daftar formulir; pembatasannya dilakukan Query, bukan DTO ini. */
final class FormListInput
{
    public function __construct(
        public readonly string $search,
        public readonly string $formType,
        public readonly string $active,
        public readonly string $sortBy,
        public readonly string $sortOrder,
        public readonly int $perPage,
    ) {}
}
