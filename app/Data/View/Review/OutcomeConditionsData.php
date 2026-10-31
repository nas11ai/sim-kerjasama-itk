<?php

namespace App\Data\View\Review;

use Spatie\LaravelData\Data;

/**
 * BR-REV-05/06 — syarat yang masih menahan keputusan otomatis, dari sisi reviewer. Hanya
 * hitungan: nama dan penilaian reviewer lain tidak ikut (BR-REV-11).
 */
final class OutcomeConditionsData extends Data
{
    public function __construct(
        public int $reviewerCount,
        public int $unsettledCount,
        public int $openThreadCount,
    ) {}
}
