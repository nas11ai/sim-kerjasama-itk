<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Queries\Submission\SubmissionAnswers;
use App\States\Submission\SubmissionStatus;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi layar detail pengajuan bagi reviewer yang bertugas. Penugasan aktifnya (BR-SM-07,
 * BR-REV-10) dijaga controller sebelum Query ini dipanggil.
 *
 * @phpstan-import-type ReviewerCard from AssignedReviewerCards
 *
 * @phpstan-type Field array{id: int, label: string, is_required: bool, order: int, field_type: array{name: string|null}, form_field_options: list<array{id: int, label: string}>}
 * @phpstan-type SubmissionCard array{id: int, is_submitted: bool, status: SubmissionStatus|null, created_at: string|null, updated_at: string|null, submitted_at: null, submitted_by: array{id: int, name: string, email: string}, form: array{id: int, title: string, description: string|null, form_fields: list<Field>}, assigned_reviewers: list<ReviewerCard>}
 * @phpstan-type View array{submission: SubmissionCard, responses: array<int, string|null>, myReviewSummary: ReviewSummary|null, reviewer: array{id: int, reviewer_type: string}, canReview: true, userRole: 'reviewer'}
 *
 * @method static View run(Reviewer $reviewer, FormSubmission $submission)
 */
class ReviewerSubmissionView implements ChangesNoState
{
    use AsAction;

    /** @return View */
    public function handle(Reviewer $reviewer, FormSubmission $submission): array
    {
        $responses = SubmissionAnswers::run($submission);

        // BR-REV-06 — layar ini menawarkan penutupan ulir, jadi yang ditunjuk ulir reviewer yang
        // masih terbuka; yang pertama dibuat bisa saja sudah ditutup sementara berikutnya menunggu.
        $mine = $submission->reviewSummaries()
            ->where('reviewer_id', $reviewer->id)
            ->with(['reviewer.user:id,name,email', 'reviewer:id,user_id,reviewer_type'])
            ->get();

        return [
            'submission' => $this->card($submission),
            'responses' => $responses,
            'myReviewSummary' => $mine->firstWhere('status', 'open') ?? $mine->first(),
            'reviewer' => ['id' => $reviewer->id, 'reviewer_type' => $reviewer->reviewer_type],
            'canReview' => true,
            'userRole' => 'reviewer',
        ];
    }

    /** @return SubmissionCard */
    private function card(FormSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'is_submitted' => $submission->is_submitted,
            'status' => $submission->status,
            'created_at' => $submission->created_at?->toISOString(),
            'updated_at' => $submission->updated_at?->toISOString(),
            // I5 — form_submissions tidak punya kolom submitted_at; selalu null sejak dulu.
            // Utangnya dilacak di final-fix-report M0.
            'submitted_at' => null,
            'submitted_by' => [
                'id' => $submission->submittedBy->id,
                'name' => $submission->submittedBy->name,
                'email' => $submission->submittedBy->email,
            ],
            'form' => [
                'id' => $submission->form->id,
                'title' => $submission->form->title,
                'description' => $submission->form->description,
                'form_fields' => array_values($submission->form->formFields->map(fn (FormField $field): array => $this->field($field))->all()),
            ],
            'assigned_reviewers' => AssignedReviewerCards::run($submission),
        ];
    }

    /** @return Field */
    private function field(FormField $field): array
    {
        return [
            'id' => $field->id,
            'label' => $field->label,
            'is_required' => $field->is_required,
            'order' => $field->order,
            'field_type' => ['name' => $field->fieldType instanceof FieldType ? $field->fieldType->name : null],
            'form_field_options' => array_values($field->formFieldOptions
                ->map(fn (FormFieldOption $option): array => ['id' => $option->id, 'label' => $option->label])
                ->all()),
        ];
    }
}
