<?php

namespace App\Actions\Monev;

use App\Actions\FormSubmission\StartChildSubmission;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\MonevStageOpenedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-10 — Operator membuka satu siklus monev: aksi tersendiri, bukan efek tanggal yang tiba
 * (jadwal berjarak bulanan; tanpa pemberitahuan tenggatnya terlanjur berjalan). W126 — tersimpan
 * di laporannya (`cycle_opened_at`), jadi hanya terjadi sekali.
 *
 * @method static int run(FormSubmission $parent, FormPhaseDetail $detail, User $operator)
 */
class OpenMonevCycle
{
    use AsAction;

    /** @return int jumlah orang yang diberi tahu */
    public function handle(FormSubmission $parent, FormPhaseDetail $detail, User $operator): int
    {
        // BR-MON-01/02/08 — gerbangnya di StartChildSubmission. W126 — laporannya dipastikan ada
        // (milik ketua induk), supaya reviewer dan perpanjangan bisa diatur sebelum peneliti mulai.
        $report = StartChildSubmission::run($parent, $detail, $operator);

        DB::transaction(fn () => $this->markOpened($report));

        $recipients = $this->recipients($parent, $report);

        if ($recipients->isNotEmpty()) {
            Deliver::to($recipients, new MonevStageOpenedNotification($parent, $detail));
        }

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Siklus monev dibuka', $report, $operator);

        return $recipients->count();
    }

    /** [MON-REVIEW-14] — baris dikunci, sehingga dua klik bersamaan tidak sama-sama lolos. */
    private function markOpened(FormSubmission $report): void
    {
        $locked = FormSubmission::query()->lockForUpdate()->findOrFail($report->id);

        if ($locked->cycle_opened_at !== null) {
            throw ValidationException::withMessages([
                'form_phase_detail_id' => 'Siklus ini sudah diaktifkan pada '.$locked->cycle_opened_at->translatedFormat('d F Y').'.',
            ]);
        }

        $locked->cycle_opened_at = now()->toImmutable();
        $locked->save();
    }

    /**
     * Yang diberi tahu adalah **pihak yang harus bertindak**: peneliti karena laporannya
     * ditunggu, dan reviewer yang ditugaskan pada siklus itu karena penilaiannya ditunggu.
     * Reviewer siklus lain tidak — penugasan monev tidak pernah diwarisi (BR-MON-06).
     *
     * @return Collection<int, User>
     */
    private function recipients(FormSubmission $parent, FormSubmission $report): Collection
    {
        $reviewerUsers = User::query()
            ->whereIn('id', $report->submissionReviewers()
                ->where('status', 'active')
                ->join('reviewers', 'reviewers.id', '=', 'submission_reviewers.reviewer_id')
                ->select('reviewers.user_id'))
            ->get();

        return collect([$parent->submittedBy])->filter()->concat($reviewerUsers)->unique('id')->values();
    }
}
