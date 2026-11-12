<?php

namespace App\Actions\Review;

use App\Models\ReviewRound;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-19, BR-REV-16 — penilaian kursi ini dibuka ulang, jadi tanda Selesai-nya di putaran
 * terakhir dicabut; putaran yang sudah selesai siap diputuskan dibuka lagi supaya Setujui menunggu
 * reviewer menilai ulang. Status pengajuan tidak berpindah: ia masih menunggu keputusan.
 *
 * @method static void run(SubmissionReviewer $seat)
 */
class ReopenRoundSeat
{
    use AsAction;

    public function handle(SubmissionReviewer $seat): void
    {
        $round = $seat->formSubmission->latestReviewRound();

        // Laporan monev tidak berputaran; kursi yang tidak ikut putaran tidak punya tanda dicabut.
        if ($round === null) {
            return;
        }

        $unfinished = $round->participants()
            ->where('submission_reviewer_id', $seat->id)
            ->whereNotNull('finished_at')
            ->update(['finished_at' => null]);
        $reopened = $round->outcome === ReviewRound::OUTCOME_READY
            && $round->update(['completed_at' => null, 'outcome' => null]);

        if ($unfinished === 0 && !$reopened) {
            return;
        }

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Putaran review dibuka ulang untuk reviewer', $round);
    }
}
