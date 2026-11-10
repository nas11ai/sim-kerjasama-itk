<?php

namespace App\Actions\Review;

use App\Models\ReviewFormResponse;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyubmit satu jawaban evaluasi. Jawaban yang belum lengkap atau sudah
 * terkunci ditolak — syaratnya dipegang `canBeSubmitted()`.
 *
 * @method static bool run(ReviewFormResponse $response)
 */
class SubmitEvaluationForm
{
    use AsAction;

    public function handle(ReviewFormResponse $response): bool
    {
        if (!$response->canBeSubmitted()) {
            return false;
        }

        $response->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // BR-REV-05 — satu definisi hitung ulang, dipakai seluruh pemicu.
        $seat = $response->reviewerFormAssignment->submissionReviewer;
        RecalculateEvaluationStatus::run($seat);

        // BR-REV-16, ADR 0018 — mengirim lembar tidak memicu hasil review: reviewer masih boleh
        // menulis catatan lalu menekan Selesai menilai, dan baru itu yang dihitung.
        AuditTrail::record('Evaluasi disubmit', $response);

        return true;
    }
}
