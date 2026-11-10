<?php

namespace App\Actions\Concerns;

use App\Models\FormSubmission;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\UnderReview;
use RuntimeException;

/**
 * NEEDS_REVISION, APPROVED, dan REJECTED hanya bisa dimasuki dari UNDER_REVIEW. Pengajuan yang belum
 * ke sana (SUBMITTED, RESUBMITTED) dilewatkan lebih dulu, bukan dengan melebarkan state machine: satu
 * pintu ke status keputusan membuat `[REV-OUTCOME-08]` dipercaya (ADR 0017, ADR 0018).
 */
trait SettlesThroughUnderReview
{
    /** @param  class-string<SubmissionStatus>  $target */
    private function settleInto(FormSubmission $submission, string $target): void
    {
        $status = $submission->status;

        if ($status instanceof SubmissionStatus && !$status->equals(UnderReview::class)) {
            $status->transitionTo(UnderReview::class);
            $status = $submission->refresh()->status;
        }

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException("FormSubmission {$submission->id} tidak punya status — keputusan tidak bisa dicatat.");
        }

        $status->transitionTo($target);
    }
}
