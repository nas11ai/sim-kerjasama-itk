<?php

namespace App\Actions\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewFormResponse;
use App\Notifications\MonevEvaluationSubmittedNotification;
use App\Support\Notification\Deliver;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-10 — memberi tahu peneliti bahwa penilaian monevnya sudah masuk.
 *
 * Hanya untuk **laporan turunan**. Pengajuan awal sengaja tidak: skornya masih
 * ditahan sampai keputusan akhir (BR-REV-12), jadi mengabarkan "penilaian
 * masuk" hanya akan mengundang peneliti membuka halaman yang tetap kosong.
 *
 * Batas itu yang membuat pengecualian BR-MON-09 tidak pelan-pelan melebar.
 *
 *
 * **Tidak menulis audit (BR-RPT-09).** Sama dengan NotifyFormChange: pemberitahuan atas evaluasi yang sudah dicatat saat disubmit.
 *
 * @method static bool run(ReviewFormResponse $response)
 */
class NotifyMonevEvaluationSubmitted implements ChangesNoState
{
    use AsAction;

    /** @return bool apakah pemberitahuan benar-benar dikirim */
    public function handle(ReviewFormResponse $response): bool
    {
        $submission = $response->reviewerFormAssignment->submissionReviewer->formSubmission;

        // Hanya laporan turunan; pengajuan awal sengaja tidak (BR-REV-12).
        if ($submission->parent_submission_id === null) {
            return false;
        }

        Deliver::to($submission->submittedBy, new MonevEvaluationSubmittedNotification($submission));

        return true;
    }
}
