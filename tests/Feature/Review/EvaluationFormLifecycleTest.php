<?php

// tests/Feature/Review/EvaluationFormLifecycleTest.php

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\LockEvaluationForm;
use App\Actions\Review\SubmitEvaluationForm;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;

function lifecycleResponse(string $status = 'draft'): ReviewFormResponse
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = evaluationFormOn($detail, [], title: 'Substansi');

    $assignment = AssignEvaluationForm::run($reviewer, $form->id);

    return ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => $status,
    ]);
}

it('menyubmit jawaban dan menyelesaikan evaluasi reviewernya', function () {
    $response = lifecycleResponse();

    expect(SubmitEvaluationForm::run($response))->toBeTrue()
        ->and($response->fresh()->status)->toBe('submitted')
        ->and($response->fresh()->submitted_at)->not->toBeNull()
        ->and($response->reviewerFormAssignment->submissionReviewer->fresh()->evaluation_status)
        ->toBe('completed');
});

it('menolak submit jawaban yang sudah terkunci', function () {
    $response = lifecycleResponse('locked');

    expect(SubmitEvaluationForm::run($response))->toBeFalse()
        ->and($response->fresh()->status)->toBe('locked');
});

it('mengunci jawaban sekali saja', function () {
    $response = lifecycleResponse();

    expect(LockEvaluationForm::run($response))->toBeTrue()
        ->and($response->fresh()->status)->toBe('locked')
        ->and($response->fresh()->locked_at)->not->toBeNull()
        ->and(LockEvaluationForm::run($response->fresh()))->toBeFalse();
});
