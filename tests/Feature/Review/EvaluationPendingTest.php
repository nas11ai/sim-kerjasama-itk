<?php

// tests/Feature/Review/EvaluationPendingTest.php

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\PendingEvaluationCheck;

function submissionWithReviewer(string $evaluationStatus): FormSubmission
{
    $submission = FormSubmission::factory()->create();

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
    ]);

    return $submission->fresh();
}

it('menyatakan ada evaluasi tertunda saat satu reviewer belum selesai', function () {
    expect(PendingEvaluationCheck::run(submissionWithReviewer('pending')))->toBeTrue();
});

it('menyatakan tidak ada evaluasi tertunda saat seluruhnya selesai', function () {
    expect(PendingEvaluationCheck::run(submissionWithReviewer('completed')))->toBeFalse();
});

it('menganggap pengajuan tanpa reviewer tidak punya evaluasi tertunda', function () {
    expect(PendingEvaluationCheck::run(FormSubmission::factory()->create()))->toBeFalse();
});
