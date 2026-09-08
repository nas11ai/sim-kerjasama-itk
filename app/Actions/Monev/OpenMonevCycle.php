<?php

namespace App\Actions\Monev;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\MonevStageOpenedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\MonevAccessGate;
use App\Support\Notification\Deliver;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-10 — Operator membuka satu siklus monev.
 *
 * Pembukaan siklus adalah aksi tersendiri, bukan efek samping dari tanggal
 * yang tiba: jadwal monev berjarak bulanan, dan tanpa pemberitahuan tenggatnya
 * terlanjur berjalan sebelum ada yang membukanya.
 *
 * Yang diberi tahu adalah **pihak yang harus bertindak**: peneliti karena
 * laporannya ditunggu, dan reviewer yang ditugaskan pada siklus itu karena
 * penilaiannya ditunggu. Reviewer siklus lain tidak — penugasan monev tidak
 * pernah diwarisi (BR-MON-06).
 *
 * @method static int run(FormSubmission $parent, FormPhaseDetail $detail)
 */
class OpenMonevCycle
{
    use AsAction;

    /**
     * @return int jumlah orang yang diberi tahu
     */
    public function handle(FormSubmission $parent, FormPhaseDetail $detail): int
    {
        // Siklus yang belum boleh dibuka tidak memberi tahu siapa pun; gerbang
        // yang sama dipakai jalur pembuatan laporan (BR-MON-01/02/08).
        MonevAccessGate::assertCycleOpen($parent, $detail);

        $report = FormSubmission::query()
            ->where('parent_submission_id', $parent->id)
            ->where('form_phase_detail_id', $detail->id)
            ->first();

        $recipients = collect([$parent->submittedBy])->filter();

        if ($report !== null) {
            $reviewerUsers = User::query()
                ->whereIn(
                    'id',
                    $report->submissionReviewers()
                        ->where('status', 'active')
                        ->join('reviewers', 'reviewers.id', '=', 'submission_reviewers.reviewer_id')
                        ->select('reviewers.user_id')
                )
                ->get();

            $recipients = $recipients->concat($reviewerUsers);
        }

        $recipients = $recipients->unique('id')->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        Deliver::to($recipients, new MonevStageOpenedNotification($parent, $detail));

        $tercatat = $recipients->count();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Siklus monev dibuka', $parent);

        return $tercatat;
    }
}
