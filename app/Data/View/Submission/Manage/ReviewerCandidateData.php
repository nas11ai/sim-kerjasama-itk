<?php

namespace App\Data\View\Submission\Manage;

use Spatie\LaravelData\Data;

/**
 * Satu kandidat di pemilih reviewer OPERATOR-03-01/03-02. `blockedReason` = penugasan pasti ditolak
 * AssignReviewer (BR-REV-02/03); `overloaded` hanya peringatan (BR-REV-04). `type` mentah.
 */
final class ReviewerCandidateData extends Data
{
    public function __construct(
        public int $reviewerId,
        public string $name,
        public ?string $nidn,
        public string $type,
        public int $activeLoad,
        public bool $overloaded,
        public ?string $blockedReason,
    ) {}
}
