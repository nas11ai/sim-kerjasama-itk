<?php

// tests/Feature/Review/EvaluationFormsOverviewTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Queries\Review\EvaluationFormsOverview;

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

it('tidak menghitung penugasan yang sudah tidak berlaku', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);
    $form = overviewEvaluationForm($detail, 1);

    overviewAssignment($submission, $form, 'submitted');

    // Reviewer yang diganti: penugasannya sejarah, bukan pekerjaan tertunda.
    $replaced = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $replaced->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $rows = EvaluationFormsOverview::run($submission->fresh());

    expect($rows[0]['assigned_count'])->toBe(1)
        ->and($rows[0]['completion_percentage'])->toBe(100);
});

it('tidak menghitung penugasan yang sudah dicabut', function () {
    // Sisi `is_active` dari saringan: belum ada jalur produksi yang menulis
    // false, jadi tanpa test ini separuh saringan bisa dibalik tanpa ketahuan.
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);
    $form = overviewEvaluationForm($detail, 1);

    overviewAssignment($submission, $form, 'submitted');

    $active = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $active->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => false,
    ]);

    $rows = EvaluationFormsOverview::run($submission->fresh());

    expect($rows[0]['assigned_count'])->toBe(1)
        ->and($rows[0]['completion_percentage'])->toBe(100);
});
