<?php

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;

function makeSubmissionReviewer(): SubmissionReviewer
{
    $reviewer = Reviewer::create([
        'user_id' => User::factory()->create()->id,
        'reviewer_type' => 'internal',
        'start_date' => now(),
        'end_date' => now()->addYear(),
    ]);

    $submission = FormSubmission::factory()->state(['submitted_by' => User::factory()])->create();

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
    ]);
}

test('submission reviewer baru berstatus active secara default', function () {
    $sr = makeSubmissionReviewer();

    expect($sr->fresh()->status)->toBe('active');
});

test('submission reviewer bisa di-mark replaced saat reassignment', function () {
    $sr = makeSubmissionReviewer();

    $sr->update(['status' => 'replaced']);

    expect($sr->fresh()->status)->toBe('replaced');
});
