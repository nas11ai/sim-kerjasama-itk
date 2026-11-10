<?php

namespace App\Actions\Review;

use App\Enums\ReviewRoundKind;
use App\Models\ReviewRound;
use App\Models\ReviewRoundParticipant;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-16 — reviewer menekan *Selesai menilai* / *Selesai memeriksa* pada putaran berjalan.
 * Tanda itu tidak bisa dibatalkan. Peserta terakhir memicu kesimpulan putaran.
 *
 * @method static ReviewRound run(SubmissionReviewer $seat, User $actor)
 */
class FinishReviewRound
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(SubmissionReviewer $seat, User $actor): ReviewRound
    {
        $this->guardOwnActiveSeat($seat, $actor);

        $round = $seat->formSubmission->runningReviewRound() ?? $this->reject('Tidak ada putaran review yang sedang berjalan.');
        $participant = $round->participants()->where('submission_reviewer_id', $seat->id)->first()
            ?? $this->reject('Anda bukan peserta putaran review ini.');

        $this->guardNotFinished($participant);
        $this->guardEvaluationSent($round, $seat);

        $participant->update(['finished_at' => now()]);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Putaran review diselesaikan reviewer', $round, $actor);

        ConcludeReviewRound::run($round);

        return $round->refresh();
    }

    /** BR-SM-07, BR-REV-10 — hanya pemegang kursi yang masih bertugas. */
    private function guardOwnActiveSeat(SubmissionReviewer $seat, User $actor): void
    {
        if ($seat->status === 'active' && $seat->reviewer->user_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('Hanya reviewer yang bertugas yang dapat menyelesaikan putarannya.');
    }

    /** @throws ValidationException */
    private function guardNotFinished(ReviewRoundParticipant $participant): void
    {
        if ($participant->finished_at === null) {
            return;
        }

        $this->reject('Anda sudah menyelesaikan putaran ini.');
    }

    /**
     * BR-REV-16 — di putaran penilaian, Selesai menilai baru aktif setelah lembarnya terkirim.
     * Putaran pemeriksaan tidak menuntut lembar diisi ulang (BR-REV-08).
     *
     * @throws ValidationException
     */
    private function guardEvaluationSent(ReviewRound $round, SubmissionReviewer $seat): void
    {
        if ($round->kind !== ReviewRoundKind::Assessment || $seat->hasSettledEvaluation()) {
            return;
        }

        $this->reject('Kirim lembar penilaian lebih dulu.');
    }

    /** @throws ValidationException */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['round' => $message]);
    }
}
