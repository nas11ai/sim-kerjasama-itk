<?php

// tests/Feature/Review/RequiredFormsCompletionTest.php

use App\Actions\Review\AssignEvaluationForm;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Queries\Review\EvaluationFormCompletion;
use App\Queries\Review\RequiredFormsCompletion;

/** @return array{0: SubmissionReviewer, 1: FormPhaseDetail} */
function completionFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    return [$reviewer, $detail];
}

function completionForm(FormPhaseDetail $detail, int $order): ReviewEvaluationForm
{
    return evaluationFormOn($detail, [], title: "Formulir {$order}");
}

it('menganggap reviewer tanpa form wajib sudah selesai', function () {
    [$reviewer] = completionFixture();

    expect(RequiredFormsCompletion::run($reviewer))->toBeTrue();
});

it('menyatakan belum selesai selama satu form wajib belum tersubmit', function () {
    [$reviewer, $detail] = completionFixture();

    AssignEvaluationForm::run($reviewer, completionForm($detail, 1)->id, true);

    expect(RequiredFormsCompletion::run($reviewer->fresh()))->toBeFalse();
});

it('menyatakan selesai setelah seluruh form wajib tersubmit', function () {
    [$reviewer, $detail] = completionFixture();

    $assignment = AssignEvaluationForm::run($reviewer, completionForm($detail, 1)->id, true);
    ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => 'submitted',
    ]);

    expect(RequiredFormsCompletion::run($reviewer->fresh()))->toBeTrue();
});

it('mengabaikan penugasan yang sudah dicabut', function () {
    [$reviewer, $detail] = completionFixture();

    $assignment = AssignEvaluationForm::run($reviewer, completionForm($detail, 1)->id, true);
    $assignment->update(['is_active' => false]);

    expect(RequiredFormsCompletion::run($reviewer->fresh()))->toBeTrue();
});

it('menghitung form tanpa field sebagai 100 persen', function () {
    [$reviewer, $detail] = completionFixture();

    $assignment = AssignEvaluationForm::run($reviewer, completionForm($detail, 1)->id, true);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => 'draft',
    ]);

    // Form tanpa field tidak menahan apa pun, jadi ia bukan 0%.
    expect(EvaluationFormCompletion::run($response))->toBe(100);
});
