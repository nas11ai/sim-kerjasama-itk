<?php

namespace App\Data\View\Monev;

use App\Models\FormSubmission;
use App\Queries\Monev\MonevCycles;
use Spatie\LaravelData\Data;

/**
 * Kepala halaman laporan monev (RESEARCHER-05-02/03): judul induk, skema, nama siklus, tenggat
 * Batas Laporan, dan cap waktu suntingan (BR-FE-14).
 */
final class MonevReportHeaderData extends Data
{
    public function __construct(
        public int $id,
        public int $parentId,
        public string $title,
        public ?string $scheme,
        public string $cycleName,
        public ?string $deadline,
        public string $lastUpdatedAt,
    ) {}

    public static function of(FormSubmission $report, FormSubmission $parent): self
    {
        $detail = $report->formPhaseDetail;

        return new self(
            id: $report->id,
            parentId: $parent->id,
            title: $parent->form->title,
            scheme: $parent->scheme?->name,
            cycleName: $detail === null ? 'Monev' : MonevCycles::nameOf($detail),
            deadline: $detail?->submissionDate->datetime->toIso8601String(),
            lastUpdatedAt: $report->updated_at?->toISOString() ?? '',
        );
    }
}
