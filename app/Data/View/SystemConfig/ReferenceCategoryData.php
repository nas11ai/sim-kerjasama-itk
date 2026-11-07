<?php

namespace App\Data\View\SystemConfig;

use Spatie\LaravelData\Data;

/** Satu kategori data acuan ADMIN-08-10, dibangun dari ReferenceEntityRegistry. */
final class ReferenceCategoryData extends Data
{
    /** @param  list<ReferenceItemData>  $items */
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public string $usedBy,
        public bool $editable,
        public bool $deactivatable,
        public array $items,
    ) {}
}
