<?php

namespace App\Actions\Review;

use App\Enums\ReviewRoundKind;
use App\Models\ReviewRound;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-19, BR-REV-16 — kursi yang dibuka ulang harus Selesai lagi di putaran terakhir: tandanya dicabut,
 * atau ia didudukkan bila putaran itu dibuka sesudah ia Selesai (reviewer ditambah belakangan). Putaran
 * `ready` dibuka lagi supaya Setujui menunggu penilaian ulang; status pengajuan tidak berpindah.
 *
 * @method static void run(SubmissionReviewer $seat)
 */
class ReopenRoundSeat
{
    use AsAction;

    public function handle(SubmissionReviewer $seat): void
    {
        $latest = $seat->formSubmission->latestReviewRound();

        // Laporan monev tidak berputaran.
        if ($latest === null) {
            return;
        }

        // Putaran dikunci: Selesai yang berpapasan tidak menyimpulkan putaran tanpa kursi ini.
        $round = ReviewRound::query()->whereKey($latest->id)->lockForUpdate()->firstOrFail();
        $reopened = $round->outcome === ReviewRound::OUTCOME_READY
            && $round->update(['completed_at' => null, 'outcome' => null]);

        $participant = $round->participants()->firstOrNew(
            ['submission_reviewer_id' => $seat->id],
            ['kind' => ReviewRoundKind::Assessment],
        );
        $unfinished = !$participant->exists || $participant->finished_at !== null;
        $participant->finished_at = null;
        $participant->save();

        if (!$unfinished && !$reopened) {
            return;
        }

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Putaran review dibuka ulang untuk reviewer', $round);
    }
}
