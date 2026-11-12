<?php

namespace App\Actions\Review;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Submission\EvaluationDueDate;
use App\Support\Audit\AuditTrail;
use App\Support\Review\AwaitingDecision;
use App\Support\ReviewerWorkload;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menugaskan beberapa reviewer sekaligus dalam satu permintaan.
 *
 * Seluruh permintaan satu transaksi: satu penugasan yang ditolak membatalkan sisanya, sebab
 * penugasan yang tersimpan sebagian akan dianggap gagal oleh Operator.
 *
 * @method static list<string> run(FormSubmission $submission, list<int> $reviewerIds, bool $autoAssignForms)
 */
class AssignReviewers
{
    use AsAction;

    /**
     * @param  list<int>  $reviewerIds
     * @return list<string> peringatan beban, untuk ditampilkan ke Operator
     */
    public function handle(FormSubmission $submission, array $reviewerIds, bool $autoAssignForms): array
    {
        AwaitingDecision::guard($submission);

        $detail = $submission->getFormPhaseDetail();
        // null berarti fase ini tanpa form evaluasi; dicek sekali per permintaan.
        $formOwner = $detail && $detail->hasReviewEvaluationForms() ? $detail : null;

        // BR-REV-04 — beban berlebih hanya PERINGATAN: dikembalikan ke pemanggil, tak
        // pernah dilempar sebagai exception, jadi penugasan tetap berhasil dan
        // Operator yang menilai.
        $workloadWarnings = DB::transaction(
            function () use ($submission, $reviewerIds, $formOwner, $autoAssignForms): array {
                $warnings = [];

                foreach ($reviewerIds as $reviewerId) {
                    $reviewer = Reviewer::findOrFail($reviewerId);
                    $warning = $this->assignOne($submission, $reviewer, $formOwner, $autoAssignForms);

                    if ($warning !== null) {
                        $warnings[] = $warning;
                    }
                }

                return $warnings;
            }
        );

        // BR-RPT-09 — satu permintaan, satu entri audit (di samping entri tiap reviewer).
        AuditTrail::record('Penugasan reviewer diproses', $submission);

        // BR-REV-16 — kursi `not_required` dihitung selesai; bila itu peserta terakhir yang
        // ditunggu, putaran disimpulkan sekarang, sekali untuk seluruh permintaan.
        $round = $submission->runningReviewRound();

        if ($round !== null) {
            ConcludeReviewRound::run($round);
        }

        return $workloadWarnings;
    }

    /** @return string|null peringatan beban reviewer ini, bila ada */
    private function assignOne(
        FormSubmission $submission,
        Reviewer $reviewer,
        ?FormPhaseDetail $formOwner,
        bool $autoAssignForms,
    ): ?string {
        $warning = ReviewerWorkload::warningFor($submission, $reviewer);

        // BR-REV-01/02/03/09 ditegakkan AssignReviewer; jalur lama memakai firstOrCreate()
        // polos tanpa satu pun pemeriksaan. REV-ASSIGN-09: ia juga yang mengirim satu
        // notifikasi, jadi tidak ada email kedua di sini.
        $assignment = AssignReviewer::run($submission, $reviewer);

        if (!$formOwner) {
            // BR-FE-06 — tanpa form evaluasi tidak ada yang ditunggu, jadi dianggap selesai.
            $assignment->update(['evaluation_status' => 'not_required']);

            return $warning;
        }

        if ($autoAssignForms) {
            $this->assignRequiredForms($assignment, $formOwner);
        }

        return $warning;
    }

    private function assignRequiredForms(SubmissionReviewer $assignment, FormPhaseDetail $formPhaseDetail): void
    {
        foreach ($formPhaseDetail->requiredStepEvaluationForms()->get() as $step) {
            $exists = $assignment->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $step->review_evaluation_form_id)
                ->exists();

            if ($exists) {
                continue;
            }

            // BR-FE-17 — tenggat milik langkah pengajuan yang dinilai;
            // `null` jawaban yang sah (kolomnya nullable, layar menampilkan "No deadline").
            AssignEvaluationForm::run($assignment, $step->review_evaluation_form_id, true, EvaluationDueDate::run($assignment->formSubmission));
        }
    }
}
