<?php

namespace App\Actions\Review;

use App\Data\Request\ReviewEvaluationFormInput;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Komposit sementara G2-W4 untuk kartu baru di editor tahap: buat Form Evaluasi pustaka, terbitkan
 * Versi 1, lalu pasang di langkah itu. Dihapus bersama kartunya di Task 2 (pustaka Form Evaluasi).
 *
 * @method static ReviewEvaluationForm run(FormPhaseDetail $detail, ReviewEvaluationFormInput $input, User $actor)
 */
class CreateReviewEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhaseDetail $detail, ReviewEvaluationFormInput $input, User $actor): ReviewEvaluationForm
    {
        AssertReviewedPhaseDetail::run($detail->id);

        return DB::transaction(function () use ($detail, $input, $actor): ReviewEvaluationForm {
            // Audit "dibuat" menyebut langkah asal kartunya; CreateEvaluationForm (pustaka) tidak.
            $form = ReviewEvaluationForm::create($input->details()->attributes());
            AuditTrail::record('Form evaluasi dibuat', $form, $actor, new AuditChange(new: [
                ...$form->auditSnapshot(),
                'form_phase_detail_id' => $detail->id,
            ]));
            PublishEvaluationFormVersion::run($form, $input->fields ?? [], $actor);
            $step = AttachEvaluationForm::run($detail, $form, $input->isRequired, $actor);
            UpdateStepEvaluationForm::run($step, $input->isRequired, $input->isActive, $actor);

            return $form;
        });
    }
}
