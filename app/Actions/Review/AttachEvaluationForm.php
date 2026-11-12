<?php

namespace App\Actions\Review;

use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-30 — memasang Form Evaluasi pustaka di satu langkah `needs_review`, sekali per langkah,
 * paling akhir di antara pemasangan langkah itu. Hanya form yang sudah terbit: langkah tak pernah
 * memakai form tanpa versi.
 *
 * @method static StepEvaluationForm run(FormPhaseDetail $detail, ReviewEvaluationForm $form, bool $isRequired, User $actor)
 */
class AttachEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhaseDetail $detail, ReviewEvaluationForm $form, bool $isRequired, User $actor): StepEvaluationForm
    {
        AssertReviewedPhaseDetail::run($detail->id);

        if (!$form->versions()->exists()) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang.',
            ]);
        }

        if ($detail->stepEvaluationForms()->where('review_evaluation_form_id', $form->id)->exists()) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Form evaluasi ini sudah terpasang di langkah ini.',
            ]);
        }

        $step = $detail->stepEvaluationForms()->create([
            'review_evaluation_form_id' => $form->id,
            'is_required' => $isRequired,
            'is_active' => true,
            'order' => ($detail->stepEvaluationForms()->orderByDesc('order')->first()->order ?? 0) + 1,
        ]);

        AuditTrail::record('Form evaluasi dipasang', $step, $actor, new AuditChange(new: [
            'form_phase_detail_id' => $detail->id,
            'review_evaluation_form_id' => $form->id,
            'is_required' => $isRequired,
        ]));

        return $step;
    }
}
