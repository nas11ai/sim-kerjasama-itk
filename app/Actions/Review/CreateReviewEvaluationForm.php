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
 * Form evaluasi baru beserta field dan opsinya dari layar Buat Form Evaluasi, diletakkan
 * paling akhir di antara form evaluasi detail tahapnya.
 *
 * @method static ReviewEvaluationForm run(ReviewEvaluationFormInput $input, User $actor)
 */
class CreateReviewEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationFormInput $input, User $actor): ReviewEvaluationForm
    {
        AssertReviewedPhaseDetail::run($input->formPhaseDetailId);

        return DB::transaction(function () use ($input, $actor): ReviewEvaluationForm {
            $form = ReviewEvaluationForm::create([
                ...$input->attributes(),
                'order' => ReviewEvaluationForm::nextOrderFor($input->formPhaseDetailId),
            ]);
            ReviewFormFieldWriter::sync($form, $input->fields ?? []);

            AuditTrail::record('Form evaluasi dibuat', $form, $actor, new AuditChange(new: $form->auditSnapshot()));

            return $form;
        });
    }
}
