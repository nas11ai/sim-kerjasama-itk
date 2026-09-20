<?php

namespace App\Actions\Review;

use App\Models\SubmissionReviewer;
use App\Queries\Review\RequiredFormsCompletion;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-05 — menyelaraskan `evaluation_status` dengan penugasan yang berlaku.
 * Jejaknya ditulis hanya saat status benar-benar berpindah, jadi satu kejadian
 * tidak pernah menghasilkan dua entri yang mengatakan hal yang sama.
 *
 * @method static void run(SubmissionReviewer $reviewer)
 */
class RecalculateEvaluationStatus
{
    use AsAction;

    public function handle(SubmissionReviewer $reviewer): void
    {
        $baru = $this->statusFor($reviewer);

        if ($reviewer->evaluation_status === $baru) {
            return;
        }

        $reviewer->update(['evaluation_status' => $baru]);

        // BR-RPT-09 — perpindahan status evaluasi fakta tersendiri, bukan
        // bayangan dari peristiwa yang memicunya.
        AuditTrail::record('Status evaluasi diperbarui', $reviewer);
    }

    private function statusFor(SubmissionReviewer $reviewer): string
    {
        // Tidak ada yang ditunggu dari reviewer ini — BR-FE-06 memperlakukannya
        // sebagai selesai, bukan menggantungnya di `pending` selamanya.
        if (!$reviewer->activeReviewerFormAssignments()->exists()) {
            return 'not_required';
        }

        return RequiredFormsCompletion::run($reviewer) ? 'completed' : 'pending';
    }
}
