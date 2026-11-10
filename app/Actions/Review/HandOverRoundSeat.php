<?php

namespace App\Actions\Review;

use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-10, BR-REV-16 — pengganti mengambil kursi reviewer yang diganti di putaran berjalan,
 * sebagai peserta yang **belum** selesai: tanda Selesai milik orang, bukan kursi. Kursi lama
 * dilepas supaya putaran tidak menunggu reviewer yang sudah tidak berwenang.
 *
 * @method static void run(SubmissionReviewer $outgoing, SubmissionReviewer $incoming)
 */
class HandOverRoundSeat
{
    use AsAction;

    public function handle(SubmissionReviewer $outgoing, SubmissionReviewer $incoming): void
    {
        $round = $outgoing->formSubmission->runningReviewRound();

        if ($round === null) {
            return;
        }

        $removed = $round->participants()->where('submission_reviewer_id', $outgoing->id)->delete();

        if ($removed === 0) {
            return;
        }

        $round->participants()->firstOrCreate(['submission_reviewer_id' => $incoming->id]);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Kursi putaran review diserahkan ke pengganti', $round);
    }
}
