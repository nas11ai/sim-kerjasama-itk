<?php

namespace App\Support\Review;

use App\Models\SubmissionReviewer;
use App\Queries\Review\RequiredFormsCompletion;

/**
 * BR-REV-05 — menyelaraskan `evaluation_status` dengan penugasan yang berlaku.
 * `final class` statis mengikuti preseden `RequiredFieldGuard`: memulihkan
 * invarian bukan use case, dan Action akan menulis audit ganda per kejadian.
 */
final class EvaluationStatusRecalculator
{
    public static function for(SubmissionReviewer $reviewer): void
    {
        $reviewer->update(['evaluation_status' => self::statusFor($reviewer)]);
    }

    private static function statusFor(SubmissionReviewer $reviewer): string
    {
        // Tidak ada yang ditunggu dari reviewer ini — BR-FE-06 memperlakukannya
        // sebagai selesai, bukan menggantungnya di `pending` selamanya.
        if (!$reviewer->activeReviewerFormAssignments()->exists()) {
            return 'not_required';
        }

        return RequiredFormsCompletion::run($reviewer) ? 'completed' : 'pending';
    }
}
