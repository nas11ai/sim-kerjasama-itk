<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu kartu ADMIN-08-08: tahap beserta hitungan pakainya, semuanya dari backend (W101, ADR 0019).
 * `submissionFormTitle` = Form langkah Pengajuan (BR-FE-28); `versionLabel` null = belum pernah
 * terbit, `hasDraft` = ada struktur yang belum terbit (BR-FE-33).
 */
final class PhaseCardData extends Data
{
    /**
     * @param  list<string>  $periodNames
     * @param  list<array{value: string, label: string}>  $linkablePeriods
     * @param  list<array{period: string, count: int}>  $deadlineGaps
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public bool $isActive,
        public int $detailCount,
        public array $periodNames,
        public int $submissionCount,
        public array $linkablePeriods,
        public array $deadlineGaps,
        public ?string $submissionFormTitle,
        public ?string $versionLabel,
        public bool $hasDraft,
    ) {}
}
