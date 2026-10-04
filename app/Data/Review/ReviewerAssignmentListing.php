<?php

namespace App\Data\Review;

use App\Models\ReviewerFormAssignment;
use Illuminate\Pagination\LengthAwarePaginator;

/** Satu halaman daftar tugas reviewer beserta statistik seluruh tugasnya. */
final class ReviewerAssignmentListing
{
    /**
     * @param  LengthAwarePaginator<int, ReviewerFormAssignment>  $assignments
     * @param  array{total: int, pending: int, completed: int, overdue: int}  $stats
     */
    public function __construct(
        public readonly LengthAwarePaginator $assignments,
        public readonly array $stats,
    ) {}
}
