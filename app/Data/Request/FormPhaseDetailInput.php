<?php

namespace App\Data\Request;

/**
 * Satu baris detail tahap. `id` null berarti detail baru. Tenggat yang null pada detail lama
 * mempertahankan nilainya: BR-FE-07 mewajibkan setiap detail punya deadline, dan BR-FE-26
 * memberinya tiga — Batas Submit, Batas Evaluasi (wajib bila direview), Batas Revisi.
 */
final class FormPhaseDetailInput
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $formAccessControlId,
        public readonly int $phaseTypeId,
        public readonly bool $needsReview,
        public readonly ?int $submissionDateId = null,
        public readonly ?int $evaluationDateId = null,
        public readonly ?int $revisionDateId = null,
    ) {}

    /** @return array{submission_date_id: int|null, evaluation_date_id: int|null, revision_date_id: int|null} */
    public function deadlineIds(): array
    {
        return [
            'submission_date_id' => $this->submissionDateId,
            'evaluation_date_id' => $this->evaluationDateId,
            'revision_date_id' => $this->revisionDateId,
        ];
    }
}
