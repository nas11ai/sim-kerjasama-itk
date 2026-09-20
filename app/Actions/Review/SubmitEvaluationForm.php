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
        RecalculateEvaluationStatus::run($response->reviewerFormAssignment->submissionReviewer);

        AuditTrail::record('Evaluasi disubmit', $response);

        return true;
    }
}
