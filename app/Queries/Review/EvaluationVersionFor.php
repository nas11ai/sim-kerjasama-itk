<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\EvaluationFormPin;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-18 — versi yang berlaku bagi `$form` pada angkatan × langkah pengajuan ini: versi pakuan,
 * atau versi terbit terbaru selama belum ada draf penilaian di sana. Membaca saja; pemakuannya
 * PinEvaluationVersion. Form yang dipasang selalu sudah terbit (G2-W3), jadi versinya selalu ada.
 *
 * @method static ReviewEvaluationFormVersion run(FormSubmission $submission, ReviewEvaluationForm $form)
 */
class EvaluationVersionFor implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission, ReviewEvaluationForm $form): ReviewEvaluationFormVersion
    {
        $pin = EvaluationFormPin::query()
            ->where('submission_period_id', $submission->submission_period_id)
            ->where('form_phase_detail_id', $submission->form_phase_detail_id)
            ->where('review_evaluation_form_id', $form->id)
            ->first();

        return $pin->reviewEvaluationFormVersion ?? $form->latestVersion()->firstOrFail();
    }
}
