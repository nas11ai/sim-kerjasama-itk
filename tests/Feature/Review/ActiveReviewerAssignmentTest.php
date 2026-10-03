<?php

// BR-SM-07, BR-REV-10 — satu definisi "penugasan reviewer yang masih berlaku".

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;

function assignmentOf(FormSubmission $submission, Reviewer $reviewer, string $status): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => $status,
    ]);
}

it('menemukan penugasan yang masih berlaku', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    $assignment = assignmentOf($submission, $reviewer, 'active');

    expect(ActiveReviewerAssignment::run($submission, $reviewer->id)?->id)->toBe($assignment->id);
});

it('mengabaikan penugasan yang sudah diganti', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    assignmentOf($submission, $reviewer, 'replaced');

    expect(ActiveReviewerAssignment::run($submission, $reviewer->id))->toBeNull();
});

it('tidak tertukar dengan penugasan reviewer lain atau pengajuan lain', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    $other = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    assignmentOf($submission, $other, 'active');
    assignmentOf(FormSubmission::factory()->create(), $reviewer, 'active');

    expect(ActiveReviewerAssignment::run($submission, $reviewer->id))->toBeNull();
});
