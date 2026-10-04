<?php

namespace App\Data\Review;

/** Hasil penugasan form massal: berapa reviewer yang diproses dan siapa yang dilewati. */
final class BulkFormAssignmentResult
{
    /** @param  list<int>  $skippedReviewerIds reviewer yang tidak bertugas pada pengajuan itu */
    public function __construct(
        public readonly int $assignedCount,
        public readonly array $skippedReviewerIds,
    ) {}
}
