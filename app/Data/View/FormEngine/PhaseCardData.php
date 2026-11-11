<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu kartu ADMIN-08-08: tahap beserta hitungan pakainya. `linkablePeriods` dan
 * `deadlineGaps` diisi backend (W101; celah jadwal per periode, ADR 0019); layar tidak menghitung apa pun.
 * `submissionFormTitle` = Form langkah Pengajuan (BR-FE-28), null bila tahap belum punya langkah itu.
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
    ) {}
}
