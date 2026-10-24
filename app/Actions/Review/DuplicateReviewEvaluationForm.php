<?php

namespace App\Actions\Review;

use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Salinan form evaluasi beserta field dan opsinya di detail tahap yang sama, diletakkan
 * paling akhir. Penugasan dan jawaban tidak ikut tersalin.
 *
 * @method static ReviewEvaluationForm run(ReviewEvaluationForm $form, User $actor)
 */
class DuplicateReviewEvaluationForm
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form, User $actor): ReviewEvaluationForm
    {
        return DB::transaction(function () use ($form, $actor): ReviewEvaluationForm {
            $copy = $form->replicate();
            $copy->title = $form->title.' (Copy)';
            $copy->order = ReviewEvaluationForm::nextOrderFor($form->form_phase_detail_id);
            $copy->save();

            foreach ($form->reviewFormFields as $field) {
                $newField = $field->replicate();
                $newField->reviewEvaluationForm()->associate($copy)->save();

                foreach ($field->reviewFormFieldOptions as $option) {
                    $option->replicate()->reviewFormField()->associate($newField)->save();
                }
            }

            AuditTrail::record('Form evaluasi diduplikasi', $copy, $actor, new AuditChange(new: [
                ...$copy->auditSnapshot(),
                'source_evaluation_form_id' => $form->id,
            ]));

            return $copy;
        });
    }
}
