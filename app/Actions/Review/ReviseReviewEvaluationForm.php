<?php

namespace App\Actions\Review;

use App\Data\Request\ReviewEvaluationFormInput;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Services\Form\ReviewFormFieldWriter;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyunting form evaluasi dari kartunya di editor tahap. Detailnya tetap: route ubahnya menunjuk
 * form, bukan detail, jadi form tidak bisa dipindah ke detail lain (W100).
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
        AssertReviewedPhaseDetail::run($input->formPhaseDetailId);

        DB::transaction(function () use ($form, $input, $actor): void {
            $old = $form->auditSnapshot();
            $form->update($input->attributes());

            if ($input->fields !== null) {
                ReviewFormFieldWriter::sync($form, $input->fields);
            }

            AuditTrail::record('Form evaluasi diubah', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
        });
    }
}
