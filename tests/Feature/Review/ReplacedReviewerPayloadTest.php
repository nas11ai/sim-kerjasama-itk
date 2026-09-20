<?php

// tests/Feature/Review/ReplacedReviewerPayloadTest.php
//
// BR-REV-05 menghitung reviewer aktif saja, dan BR-REV-15 menyimpan yang
// diganti sebagai sejarah. Layar wajib memakai definisi yang sama dengan
// EvaluationProgress — kalau tidak, "3 reviewer" berdiri di sebelah
// "100% selesai (2/2)" pada pengajuan yang sama.

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Review\EvaluationProgress;
use Inertia\Testing\AssertableInertia as Assert;

function payloadReviewer(FormSubmission $submission, string $status, string $evaluationStatus): void
{
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => User::factory()->create()->id])->id,
        'evaluation_status' => $evaluationStatus,
        'status' => $status,
    ]);
}

it('tidak menghitung reviewer yang diganti di ringkasan layar peneliti', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $owner->id]);

    payloadReviewer($submission, 'active', 'completed');
    payloadReviewer($submission, 'active', 'completed');
    payloadReviewer($submission, 'replaced', 'pending');

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewStats.total_reviewers', 2)
            ->count('assignedReviewers', 2)
            ->etc());
});

it('sepakat dengan EvaluationProgress atas pengajuan yang sama', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $owner->id]);

    payloadReviewer($submission, 'active', 'completed');
    payloadReviewer($submission, 'active', 'completed');
    payloadReviewer($submission, 'replaced', 'pending');

    $progress = EvaluationProgress::run($submission->fresh());

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewStats.total_reviewers', $progress->totalReviewers)
            ->etc());
});
