<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\ReviewSummary;
use App\Models\ReviewSummaryAttachment;
use App\Models\SubmissionReviewer;
use App\Notifications\ReviewThreadOpenedNotification;
use App\Queries\Review\DiscussionParticipants;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use App\Support\ReviewSummaryGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Membuka satu ulir review beserta lampirannya.
 *
 * @method static ReviewSummary run(FormSubmission $submission, SubmissionReviewer $assignment, string $summaryNotes, list<string> $attachmentPaths = [])
 */
class OpenReviewThread
{
    use AsAction;

    /** @param  list<string>  $attachmentPaths */
    public function handle(
        FormSubmission $submission,
        SubmissionReviewer $assignment,
        string $summaryNotes,
        array $attachmentPaths = [],
    ): ReviewSummary {
        // BR-FE-06 — satu definisi "evaluasi beres", yang sama dengan BR-REV-05
        // dan BR-REV-06.
        ReviewSummaryGuard::assertMayCreate($assignment);
        $this->guardRunningSeat($submission, $assignment);

        $summary = DB::transaction(function () use ($submission, $assignment, $summaryNotes, $attachmentPaths): ReviewSummary {
            $summary = ReviewSummary::create([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $assignment->reviewer_id,
                'status' => 'open',
                'summary_notes' => $summaryNotes,
            ]);

            foreach ($attachmentPaths as $path) {
                ReviewSummaryAttachment::create([
                    'review_summary_id' => $summary->id,
                    'file_path' => $path,
                ]);
            }

            $this->moveToReview($submission);

            return $summary;
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Ulir review dibuka', $summary);

        // BR-NOT-03 — dikirim sesudah ulirnya tersimpan; pembukanya tak perlu diberi tahu.
        Deliver::to(
            DiscussionParticipants::run($submission, $assignment->reviewer->user_id),
            new ReviewThreadOpenedNotification($summary),
            ['review_summary_id' => $summary->id],
        );

        return $summary;
    }

    /**
     * BR-REV-17 — catatan pengajuan utama hanya dari peserta putaran berjalan yang belum Selesai: catatan
     * sesudah putaran disimpulkan mengunci Setujui tanpa ada putaran yang memicu revisi, jadi pengajuan
     * buntu (keputusan rencana P1-W3). Laporan monev tidak punya putaran (BR-MON-02).
     *
     * @throws ValidationException
     */
    private function guardRunningSeat(FormSubmission $submission, SubmissionReviewer $assignment): void
    {
        if ($submission->parent_submission_id !== null) {
            return;
        }

        $mayOpen = $submission->runningReviewRound()?->participants()
            ->where('submission_reviewer_id', $assignment->id)
            ->whereNull('finished_at')
            ->exists();

        if ($mayOpen === true) {
            return;
        }

        throw ValidationException::withMessages([
            'review_summary' => 'Catatan hanya bisa dibuka selama putaran Anda berjalan.',
        ]);
    }

    /** Ulir pertama menandai review sudah berjalan; yang sudah berjalan dibiarkan. */
    private function moveToReview(FormSubmission $submission): void
    {
        $status = $submission->status;

        if (!$status instanceof Submitted) {
            return;
        }

        $status->transitionTo(UnderReview::class);
    }
}
