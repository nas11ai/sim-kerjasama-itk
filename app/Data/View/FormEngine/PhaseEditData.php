<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/** Tahap di editor ADMIN-08-09 (W94); `id` null = mode buat, detail kosongnya dibuat layar. */
final class PhaseEditData extends Data
{
    /**
     * @param  list<PhaseDetailEditData>  $details  urut `order`, lalu `id` — versi terbaru (BR-FE-32)
     * @param  string|null  $versionLabel  "Versi n" terbaru; null di mode buat
     */
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $description,
        public bool $isActive,
        public array $details,
        public ?string $versionLabel = null,
    ) {}
}
