<?php

namespace App\Actions\Review;

use App\Enums\ReviewRoundKind;
use App\Models\FormSubmission;
use App\Models\ReviewRound;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\RecheckRequestedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-16 — peneliti mengirim ulang revisi: putaran pemeriksaan dibuka untuk reviewer aktif
 * yang catatannya masih `open`. Reviewer tanpa catatan terbuka tidak diminta memeriksa, dan
 * lembar penilaian tidak diisi ulang (BR-REV-08).
 *
 * @method static ReviewRound run(FormSubmission $submission)
 */
class StartRecheckRound
{
    use AsAction;

    public function handle(FormSubmission $submission): ReviewRound
    {
        $seats = $this->seatsWithOpenNotes($submission);

        $round = DB::transaction(function () use ($submission, $seats): ReviewRound {
            // Putaran penilaian bisa masih berjalan bila Operator menambah reviewer selama
            // NEEDS_REVISION; pemilik catatan bergabung ke sana, bukan membuka putaran kedua.
            $round = $submission->runningReviewRound() ?? $submission->reviewRounds()->create([
                'number' => ($submission->latestReviewRound()->number ?? 0) + 1,
                'kind' => ReviewRoundKind::Recheck,
            ]);

            foreach ($seats as $seat) {
                $round->participants()->firstOrCreate(['submission_reviewer_id' => $seat->id]);
            }

            return $round;
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Putaran pemeriksaan dibuka', $round);

        // Spec §4.8 — yang diberi tahu adalah pihak yang harus bertindak (sejalan BR-MON-10).
        Deliver::to($this->usersOf($seats), new RecheckRequestedNotification($submission));

        // Semua catatan sudah ditutup selama NEEDS_REVISION → tidak ada yang perlu memeriksa;
        // putaran tanpa peserta disimpulkan sekarang dan Operator diberi tahu.
        ConcludeReviewRound::run($round);

        return $round->refresh();
    }

    /** @return Collection<int, SubmissionReviewer> */
    private function seatsWithOpenNotes(FormSubmission $submission): Collection
    {
        $holders = $submission->reviewSummaries()->where('status', 'open')->select('reviewer_id');

        return $submission->activeSubmissionReviewers()
            ->whereIn('reviewer_id', $holders)
            ->with('reviewer.user')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * @param  Collection<int, SubmissionReviewer>  $seats
     * @return Collection<int, User>
     */
    private function usersOf(Collection $seats): Collection
    {
        return $seats->map(fn (SubmissionReviewer $seat): ?User => $seat->reviewer->user)->filter()->values();
    }
}
