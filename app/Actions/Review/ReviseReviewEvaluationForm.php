<?php

namespace App\Actions\Review;

use App\Data\Request\ReviewEvaluationFormInput;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Komposit sementara G2-W4 untuk kartu di editor tahap: ubah nama pustaka, terbitkan versi baru bila
 * kartu mengirim field, dan ubah wajib/aktif pemasangannya. Form kartu terpasang di tepat satu
 * langkah (lahir lewat CreateReviewEvaluationForm). Dihapus bersama kartunya di Task 2.
 *
 * @method static void run(ReviewEvaluationForm $form, ReviewEvaluationFormInput $input, User $actor)
 */
class ReviseReviewEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationForm $form, ReviewEvaluationFormInput $input, User $actor): void
    {
        $step = $form->stepEvaluationForms()->firstOrFail();
        AssertReviewedPhaseDetail::run($step->form_phase_detail_id);

        DB::transaction(function () use ($form, $step, $input, $actor): void {
            $old = $form->auditSnapshot();
            $form->update($input->details()->attributes());
            if ($form->wasChanged()) {
                AuditTrail::record('Form evaluasi diubah', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
            }

            if ($input->fields !== null) {
                PublishEvaluationFormVersion::run($form, $input->fields, $actor);
            }

            UpdateStepEvaluationForm::run($step, $input->isRequired, $input->isActive, $actor);
        });
    }
}
