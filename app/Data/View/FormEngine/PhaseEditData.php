<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/** Tahap di editor ADMIN-08-09 (W94); `id` null = mode buat, langkah kosongnya dibuat layar. */
final class PhaseEditData extends Data
{
    /**
     * @param  list<PhaseDetailEditData>  $steps  draf bila ada, selain itu versi terbaru (BR-FE-33)
     * @param  string|null  $versionLabel  "Versi n" terbaru; null = belum pernah terbit
     * @param  list<array{label: string, publishedAt: string, publishedBy: string|null, cohortCount: int}>  $versions  terbaru dulu
     * @param  array{nextLabel: string, pinnedCohorts: int}  $impact  ringkasan konfirmasi Terbitkan
     */
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $description,
        public bool $isActive,
        public array $steps,
        public bool $hasDraft,
        public ?string $versionLabel,
        public array $versions,
        public array $impact,
    ) {}
}
