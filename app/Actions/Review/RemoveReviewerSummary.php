<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mencabut ReviewSummary seorang reviewer. False kalau tidak ada yang tercabut.
 *
 * @method static bool run(FormSubmission $submission, int $reviewerId)
 */
class RemoveReviewerSummary
{
    use AsAction;

    public function handle(FormSubmission $submission, int $reviewerId): bool
    {
        $removed = $submission->reviewSummaries()
            ->where('reviewer_id', $reviewerId)
            ->delete();

        if ($removed === 0) {
            return false;
        }

        // BR-RPT-09 — ulirnya sudah tiada, jadi yang dicatat pengajuannya.
        AuditTrail::record(
            'Ulir review reviewer dicabut',
            $submission,
            null,
            ['reviewer_id' => $reviewerId, 'review_summaries' => $removed],
            null,
        );

        return true;
    }
}
