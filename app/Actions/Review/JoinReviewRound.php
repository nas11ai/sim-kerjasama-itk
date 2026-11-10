<?php

namespace App\Actions\Review;

use App\Enums\ReviewRoundKind;
use App\Models\FormSubmission;
use App\Models\ReviewRound;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-16 — kursi reviewer yang baru ditugaskan ikut putaran penilaian. Tidak ada putaran
 * berjalan → putaran penilaian baru dibuka berisi kursi ini. Putaran pemeriksaan berjalan →
 * kursi ini tidak ikut: ia tidak punya catatan untuk diperiksa (keputusan rencana P1-W2).
 *
 * @method static void run(SubmissionReviewer $seat)
 */
class JoinReviewRound
{
    use AsAction;

    public function handle(SubmissionReviewer $seat): void
    {
        $submission = $seat->formSubmission;

        // BR-MON-02 — laporan monev punya lifecycle sendiri dan tidak pernah diputuskan.
        if ($submission->parent_submission_id !== null) {
            return;
        }

        $round = DB::transaction(function () use ($submission, $seat): ?ReviewRound {
            // Dua penugasan bersamaan tidak boleh sama-sama membuka putaran bernomor sama.
            FormSubmission::query()->whereKey($submission->id)->lockForUpdate()->value('id');
            $round = $submission->runningReviewRound() ?? $this->openAssessment($submission);

            if ($round->kind === ReviewRoundKind::Recheck) {
                return null;
            }

            $round->participants()->firstOrCreate(
                ['submission_reviewer_id' => $seat->id],
                ['kind' => ReviewRoundKind::Assessment],
            );

            return $round;
        });

        if ($round === null) {
            return;
        }

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Reviewer masuk putaran review', $round);
    }

    private function openAssessment(FormSubmission $submission): ReviewRound
    {
        return $submission->reviewRounds()->create([
            'number' => ($submission->latestReviewRound()->number ?? 0) + 1,
            'kind' => ReviewRoundKind::Assessment,
        ]);
    }
}
