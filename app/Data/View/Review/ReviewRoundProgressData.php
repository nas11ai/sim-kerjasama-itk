<?php

namespace App\Data\View\Review;

use App\Models\ReviewRound;
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

    public static function of(ReviewRound $round): self
    {
        $total = $round->participants()->count();

        return new self(
            kind: $round->kind->value,
            number: $round->number,
            finished: $total - $round->unfinishedParticipants()->count(),
            total: $total,
        );
    }
}
