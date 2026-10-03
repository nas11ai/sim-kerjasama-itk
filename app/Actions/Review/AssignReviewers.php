<?php

namespace App\Actions\Review;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Queries\Submission\EvaluationDueDate;
use App\Support\Audit\AuditTrail;
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
        $formPhaseDetail = $submission->getFormPhaseDetail();

        // BR-REV-04 — beban berlebih memberi PERINGATAN, bukan penolakan. Peringatan
        // dikumpulkan di luar penugasan itu sendiri: yang perlu Operator ketahui adalah
        // konsekuensinya, bukan kegagalan, dan ia ikut terbawa ke layar.
        $peringatanBeban = DB::transaction(function () use ($submission, $reviewerIds, $formPhaseDetail, $autoAssignForms): array {
            $peringatan = [];

            foreach ($reviewerIds as $reviewerId) {
                $beban = $this->assignOne($submission, Reviewer::findOrFail($reviewerId), $formPhaseDetail, $autoAssignForms);

                if ($beban !== null) {
                    $peringatan[] = $beban;
                }
            }

            return $peringatan;
        });

        // BR-RPT-09 — satu permintaan, satu entri audit (di samping entri tiap reviewer).
        AuditTrail::record('Reviewer ditugaskan', $submission);

        return $peringatanBeban;
    }

    /** @return string|null peringatan beban reviewer ini, bila ada */
    private function assignOne(FormSubmission $submission, Reviewer $reviewer, ?FormPhaseDetail $formPhaseDetail, bool $autoAssignForms): ?string
    {
        $beban = ReviewerWorkload::warningFor($submission, $reviewer);

        // BR-REV-01/02/03/09 ditegakkan AssignReviewer; jalur lama memakai firstOrCreate()
        // polos tanpa satu pun pemeriksaan. REV-ASSIGN-09: ia juga yang mengirim satu
        // notifikasi, jadi tidak ada email kedua di sini.
        $assignment = AssignReviewer::run($submission, $reviewer);

        if (!$formPhaseDetail || !$formPhaseDetail->hasReviewEvaluationForms()) {
            // BR-FE-06 — tanpa form evaluasi tidak ada yang ditunggu, jadi dianggap selesai.
            $assignment->update(['evaluation_status' => 'not_required']);

            return $beban;
        }

        if ($autoAssignForms) {
            $this->assignRequiredForms($assignment, $formPhaseDetail);
        }

        return $beban;
    }

    private function assignRequiredForms(SubmissionReviewer $assignment, FormPhaseDetail $formPhaseDetail): void
    {
        foreach ($formPhaseDetail->requiredReviewEvaluationForms()->get() as $form) {
            /** @var ReviewEvaluationForm $form */
            $exists = $assignment->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // BR-FE-17 — tenggat milik detail yang memiliki form ini, dihitung per form;
            // `null` jawaban yang sah (kolomnya nullable, layar menampilkan "No deadline").
            AssignEvaluationForm::run($assignment, $form->id, true, EvaluationDueDate::run($form));
        }
    }
}
