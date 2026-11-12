<?php

namespace App\Actions\Review;

use App\Models\ReviewerFormAssignment;
use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Melepas Form Evaluasi dari satu langkah. BR-FE-22 — form yang sudah ditugaskan ke reviewer pada
 * pengajuan di langkah itu adalah dasar penilaiannya, jadi hanya bisa dinonaktifkan.
 *
 * @method static void run(StepEvaluationForm $step, User $actor)
 */
class DetachEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(StepEvaluationForm $step, User $actor): void
    {
        $assigned = ReviewerFormAssignment::query()
            ->where('review_evaluation_form_id', $step->review_evaluation_form_id)
            ->whereHas('submissionReviewer.formSubmission', fn (Builder $q) => $q->where('form_phase_detail_id', $step->form_phase_detail_id))
            ->count();

        if ($assigned > 0) {
            throw ValidationException::withMessages([
                'step_evaluation_form' => "Form evaluasi ini sudah ditugaskan ke {$assigned} reviewer di langkah ini, jadi tidak bisa dilepas. Nonaktifkan saja.",
            ]);
        }

        DB::transaction(function () use ($step, $actor): void {
            AuditTrail::record('Form evaluasi dilepas', $step, $actor, new AuditChange(old: [
                'form_phase_detail_id' => $step->form_phase_detail_id,
                'review_evaluation_form_id' => $step->review_evaluation_form_id,
            ]));
            $step->delete();
        });
    }
}
