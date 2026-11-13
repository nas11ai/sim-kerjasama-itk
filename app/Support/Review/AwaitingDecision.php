<?php

namespace App\Support\Review;

use App\Models\FormSubmission;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Validation\ValidationException;

/**
 * BR-REV-01, BR-SM-05 — pengajuan terkirim yang belum diputuskan: rentang penugasan reviewer Operator.
 * Dibaca Policy (tombol) dan Action penugasan Operator, karena Super Admin lolos Policy lewat Gate::before.
 */
final class AwaitingDecision
{
    public static function holds(FormSubmission $submission): bool
    {
        return $submission->status instanceof Submitted
            || $submission->status instanceof UnderReview
            || $submission->status instanceof NeedsRevision
            || $submission->status instanceof Resubmitted;
    }

    /**
     * Tidak di AssignReviewer: penugasan reviewer monev (M1) memakainya di luar rentang ini.
     *
     * @throws ValidationException
     */
    public static function guard(FormSubmission $submission): void
    {
        if (self::holds($submission)) {
            return;
        }

        throw ValidationException::withMessages([
            'reviewer_id' => 'Penilai hanya dapat ditugaskan pada pengajuan terkirim yang belum diputuskan.',
        ]);
    }
}
