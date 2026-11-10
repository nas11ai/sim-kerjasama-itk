<?php

namespace App\Actions\Review;

use App\Actions\Concerns\SettlesThroughUnderReview;
use App\Models\FormSubmission;
use App\Models\ReviewRound;
use App\Models\User;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\SubmissionReadyForDecisionNotification;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-06, BR-REV-16 — satu-satunya tempat hasil review dihitung, sekali per putaran: catatan `open`
 * → NEEDS_REVISION serentak; tidak ada → siap diputuskan. Jalur ke APPROVED/REJECTED tidak boleh ada di
 * sini — keduanya keputusan Operator (BR-REV-05, BR-REV-07, BR-REV-14, `[REV-OUTCOME-08]`, ADR 0018).
 *
 * @method static bool run(ReviewRound $round)
 */
class ConcludeReviewRound
{
    use AsAction;
    use SettlesThroughUnderReview;

    /** Status yang menunggu keputusan; hanya di sini hasil putaran memindahkan status. */
    private const AWAITING_DECISION = [Submitted::class, UnderReview::class, Resubmitted::class];

    public function handle(ReviewRound $round): bool
    {
        $concluded = DB::transaction(fn (): ?array => $this->conclude($round));

        if ($concluded === null) {
            return false;
        }

        [$closed, $revisionRequested] = $concluded;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Putaran review selesai', $closed);

        $this->notify($closed, $revisionRequested);

        return true;
    }

    /**
     * Baris putaran dikunci: dua peserta terakhir yang menekan bersamaan menyimpulkan sekali.
     *
     * @return array{0: ReviewRound, 1: bool}|null putaran yang ditutup, dan apakah revisi baru diminta
     */
    private function conclude(ReviewRound $round): ?array
    {
        $locked = ReviewRound::query()->whereKey($round->id)->lockForUpdate()->first();

        if ($locked === null || !$locked->isRunning() || $locked->unfinishedParticipants()->exists()) {
            return null;
        }

        $submission = $locked->formSubmission;
        $needsRevision = $submission->reviewSummaries()->where('status', 'open')->exists();

        $locked->update([
            'completed_at' => now(),
            'outcome' => $needsRevision ? ReviewRound::OUTCOME_NEEDS_REVISION : ReviewRound::OUTCOME_READY,
        ]);

        // Pengajuan yang sudah NEEDS_REVISION (reviewer ditambah selama revisi) tidak diminta ulang.
        $revisionRequested = $needsRevision && $this->awaitsDecision($submission);

        if ($revisionRequested) {
            $this->settleInto($submission, NeedsRevision::class);
        }

        return [$locked, $revisionRequested];
    }

    private function awaitsDecision(FormSubmission $submission): bool
    {
        $status = $submission->status;

        return $status instanceof SubmissionStatus && $status->equals(...self::AWAITING_DECISION);
    }

    /**
     * Yang diberi tahu adalah pihak yang harus bertindak: peneliti bila revisi baru diminta,
     * Operator bila pengajuan kini menunggu keputusannya (spec §4.7). Putaran yang selesai saat
     * pengajuan sudah di luar rentang keputusan tidak memberi tahu siapa pun.
     */
    private function notify(ReviewRound $round, bool $revisionRequested): void
    {
        $submission = $round->formSubmission->refresh();

        if ($revisionRequested) {
            Deliver::to($submission->submittedBy, new RevisionRequestedNotification($submission));

            return;
        }

        if ($round->outcome !== ReviewRound::OUTCOME_READY || !$this->awaitsDecision($submission)) {
            return;
        }

        Deliver::to($this->decisionMakers(), new SubmissionReadyForDecisionNotification($submission));
    }

    /**
     * Operator dikenali dari permission lewat `whereHas`, seperti DiscussionParticipants — bukan scope
     * `permission()` Spatie yang melempar bila permission-nya belum dibuat.
     *
     * @return Collection<int, User>
     */
    private function decisionMakers(): Collection
    {
        $isOperator = fn (Builder $permission) => $permission->where('name', 'submissions.view-all');

        return User::query()
            ->where(fn (Builder $user) => $user
                ->whereHas('permissions', $isOperator)
                ->orWhereHas('roles.permissions', $isOperator))
            ->get();
    }
}
