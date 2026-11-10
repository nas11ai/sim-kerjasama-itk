<?php

namespace App\Data\View\Review;

use Spatie\LaravelData\Data;

/**
 * BR-REV-16 — "Putaran {number}: {finished} dari {total} reviewer selesai". Peserta
 * `not_required` dihitung selesai.
 */
final class ReviewRoundProgressData extends Data
{
    public function __construct(
        public string $kind,
        public int $number,
        public int $finished,
        public int $total,
    ) {}
}
