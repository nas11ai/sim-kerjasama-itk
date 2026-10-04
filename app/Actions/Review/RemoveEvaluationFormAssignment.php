<?php

namespace App\Actions\Review;

use App\Models\ReviewerFormAssignment;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mencabut penugasan satu form evaluasi dari reviewer, berikut jawaban drafnya.
 *
 * @method static void run(ReviewerFormAssignment $assignment)
 */
class RemoveEvaluationFormAssignment
{
    use AsAction;

    /**
     * @throws ValidationException bila jawabannya sudah dikirim
     */
    public function handle(ReviewerFormAssignment $assignment): void
    {
        $response = $assignment->reviewFormResponse;

        // Jawaban yang sudah dikirim adalah hasil kerja reviewer; mencabut penugasannya
        // akan menghapus evaluasi yang mungkin sudah dipakai sebagai dasar keputusan.
        if ($response?->isSubmitted()) {
            throw ValidationException::withMessages([
                'error' => 'Tidak dapat menghapus penugasan dengan respons yang telah dikirim.',
            ]);
        }

        $submissionReviewer = $assignment->submissionReviewer;

        DB::transaction(function () use ($assignment, $response, $submissionReviewer): void {
            $response?->reviewFormFieldResponses()->delete();
            $response?->delete();
            $assignment->delete();

            // BR-REV-05 — pekerjaan reviewer berkurang, jadi statusnya ikut dihitung ulang.
            RecalculateEvaluationStatus::run($submissionReviewer);
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Penugasan form evaluasi dihapus', $assignment);
    }
}
