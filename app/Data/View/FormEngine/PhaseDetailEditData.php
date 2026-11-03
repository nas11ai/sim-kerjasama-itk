<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu detail tahap di editor ADMIN-08-09 (W93). Hitungan pakai dari backend: `submissionCount`
 * mengunci form/tipe dan hapus (BR-FE-20), `assignmentCount` mengunci hapus (BR-FE-22),
 * `evaluationFormCount` mengunci needs_review (W97). `currentDeadlines` = tenggat tersimpan (W102).
 */
final class PhaseDetailEditData extends Data
{
    /** @param  list<array{id: int, type: string, cycle: int|null, periodId: int, label: string}>  $currentDeadlines */
    public function __construct(
        public int $id,
        public int $order,
        public int $formAccessControlId,
        public string $formTitle,
        public string $accessLabel,
        public int $phaseTypeId,
        public string $phaseTypeName,
        public bool $needsReview,
        public int $submissionDateId,
        public ?int $evaluationDateId,
        public ?int $revisionDateId,
        public array $currentDeadlines,
        public int $submissionCount,
        public int $assignmentCount,
        public int $evaluationFormCount,
    ) {}
}
