<?php

// tests/Feature/Review/EvaluationFormsOverviewTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Queries\Review\EvaluationFormsOverview;
use App\Queries\Review\ReviewThreadCount;
use App\Queries\Submission\EvaluationDueDate;

function overviewEvaluationForm(FormPhaseDetail $detail, int $order): ReviewEvaluationForm
{
    return ReviewEvaluationForm::create([
        'title' => "Form Evaluasi {$order}",
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'order' => $order,
    ]);
}

function overviewAssignment(FormSubmission $submission, ReviewEvaluationForm $form, string $responseStatus): void
{
    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $reviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    ReviewFormResponse::create(['reviewer_form_assignment_id' => $assignment->id, 'status' => $responseStatus]);
}

it('merangkum penugasan dan penyelesaian per form evaluasi', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);
    $substance = overviewEvaluationForm($detail, 1);
    $budget = overviewEvaluationForm($detail, 2);

    overviewAssignment($submission, $substance, 'submitted');
    overviewAssignment($submission, $substance, 'draft');

    $rows = collect(EvaluationFormsOverview::run($submission->fresh()))->map(static fn (array $row): array => [
        $row['form']->id, $row['assigned_count'], $row['completed_count'], $row['completion_percentage'],
    ]);

    expect($rows->all())->toBe([[$substance->id, 2, 1, 50], [$budget->id, 0, 0, 0]]);
});

it('menghitung ulir review per status', function () {
    $submission = FormSubmission::factory()->create();

    foreach (['open', 'open', 'resolved'] as $status) {
        ReviewSummary::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => Reviewer::factory()->create()->id,
            'status' => $status,
            'summary_notes' => 'Catatan reviewer.',
        ]);
    }

    expect(ReviewThreadCount::run($submission->fresh(), 'open'))->toBe(2)
        ->and(ReviewThreadCount::run($submission->fresh(), 'resolved'))->toBe(1);
});

it('mengembalikan null saat pengajuan tidak punya periode', function () {
    $submission = FormSubmission::factory()->create(['submission_period_id' => null]);

    expect(EvaluationDueDate::run($submission))->toBeNull();
});
