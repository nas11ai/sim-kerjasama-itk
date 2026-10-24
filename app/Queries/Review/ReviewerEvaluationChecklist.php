<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhaseDetail;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Semua form evaluasi aktif fase ini bagi satu reviewer, digabung dengan penugasan yang sudah
 * ada. Form yang belum dimulai tetap terdaftar dengan `id` null.
 *
 * @phpstan-type ChecklistItem array{id: int|null, review_evaluation_form: array{id: int, title: string, description: string|null}, form_phase_detail: array{id: int, form_title: string|null, order: int}, is_required: bool, due_date: CarbonInterface|null, review_form_response: array{id: int, status: string, submitted_at: CarbonInterface|null}|null}
 *
 * @method static list<ChecklistItem> run(SubmissionReviewer $assignment, ?FormPhaseDetail $detail)
 */
class ReviewerEvaluationChecklist implements ChangesNoState
{
    use AsAction;

    /** @return list<ChecklistItem> */
    public function handle(SubmissionReviewer $assignment, ?FormPhaseDetail $detail): array
    {
        if ($detail === null) {
            return [];
        }

        $started = $assignment->reviewerFormAssignments()
            ->with('reviewFormResponse:id,reviewer_form_assignment_id,status,submitted_at')
            ->whereHas('reviewEvaluationForm', fn (Builder $q) => $q->where('form_phase_detail_id', $detail->id))
            ->get()
            ->keyBy('review_evaluation_form_id');

        return array_values($detail->activeReviewEvaluationForms()
            ->get(['id', 'title', 'description', 'is_required', 'order'])
            ->map(fn (ReviewEvaluationForm $form): array => $this->item($form, $detail, $started->get($form->id)))
            ->all());
    }

    /** @return ChecklistItem */
    private function item(ReviewEvaluationForm $form, FormPhaseDetail $detail, ?ReviewerFormAssignment $started): array
    {
        $response = $started?->reviewFormResponse;

        return [
            'id' => $started?->id,
            'review_evaluation_form' => ['id' => $form->id, 'title' => $form->title, 'description' => $form->description],
            'form_phase_detail' => [
                'id' => $detail->id,
                'form_title' => $detail->formAccessControl?->form?->title,
                'order' => $detail->order,
            ],
            'is_required' => $form->is_required,
            'due_date' => $started?->due_date,
            'review_form_response' => $response === null ? null : [
                'id' => $response->id,
                'status' => $response->status,
                'submitted_at' => $response->submitted_at,
            ],
        ];
    }
}
