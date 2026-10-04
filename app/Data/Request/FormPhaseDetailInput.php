<?php

namespace App\Data\Request;

/**
 * Satu baris detail tahap. `id` null berarti detail baru; tenggatnya hanya dibaca
 * untuk detail baru, karena BR-FE-07 mewajibkan setiap detail punya deadline.
 */
final class FormPhaseDetailInput
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $formAccessControlId,
        public readonly int $phaseTypeId,
        public readonly bool $needsReview,
        public readonly ?int $submissionDateId = null,
    ) {}
}
