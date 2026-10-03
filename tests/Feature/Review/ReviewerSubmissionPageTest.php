<?php

// tests/Feature/Review/ReviewerSubmissionPageTest.php
//
// BR-REV-06 lewat layar detail reviewer. Satu-satunya aksi sah atas ulirnya
// sendiri adalah menutupnya; needs_revision dan rejected bukan status
// ReviewSummary (BR-REV-13), jadi layar tidak boleh menawarkannya.

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Reviewer, 2: FormSubmission} */
function reviewerPageFixture(): array
{
    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    return [$user, $reviewer, $submission];
}

function reviewerPageSummary(FormSubmission $submission, Reviewer $reviewer, string $status): ReviewSummary
{
    return ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => $status,
        'summary_notes' => 'Catatan '.$status,
    ]);
}

it('menunjuk ulir reviewer yang masih terbuka, bukan yang pertama dibuat', function () {
    [$user, $reviewer, $submission] = reviewerPageFixture();

    // Ulir lama sudah ditutup; yang bisa ditindaklanjuti hanyalah yang terbuka.
    reviewerPageSummary($submission, $reviewer, 'resolved');
    $open = reviewerPageSummary($submission, $reviewer, 'open');

    $this->actingAs($user)
        ->get(route('reviewer.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/Submissions/ShowPage')
            ->where('myReviewSummary.id', $open->id)
            ->where('myReviewSummary.status', 'open'));
});

it('tidak menawarkan status yang tidak ada di domain ini — BR-REV-13', function () {
    [$user, $reviewer, $submission] = reviewerPageFixture();
    reviewerPageSummary($submission, $reviewer, 'open');

    $this->actingAs($user)
        ->get(route('reviewer.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/Submissions/ShowPage')
            ->missing('availableStatuses'));
});
