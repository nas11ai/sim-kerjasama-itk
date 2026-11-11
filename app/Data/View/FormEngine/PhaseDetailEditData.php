<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu detail tahap di editor ADMIN-08-09 (W93). Hitungan pakai dari backend: `submissionCount`
 * mengunci form/tipe dan hapus (BR-FE-20), `assignmentCount` mengunci hapus (BR-FE-22),
 * `evaluationFormCount` mengunci needs_review (W97). Tanggal langkah milik kalender periode (ADR 0019).
 */
final class PhaseDetailEditData extends Data
{
    public function __construct(
        public int $id,
        public int $order,
        public int $formAccessControlId,
        public string $formTitle,
        public string $accessLabel,
        public int $phaseTypeId,
        public string $phaseTypeName,
        public bool $needsReview,
        public string $deadlineType,
        public ?int $deadlineCycle,
        public string $deadlineLabel,
        public int $submissionCount,
        public int $assignmentCount,
        public int $evaluationFormCount,
    ) {}
}
