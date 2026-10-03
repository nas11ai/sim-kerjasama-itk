<?php

// tests/Feature/Review/ReviewThreadAuthorPayloadTest.php
//
// Penulis ulir harus terbaca di ketiga layar pengajuan. ReviewSystem.vue
// menampilkan `reviewer.user.name` dan mencocokkan `reviewer.user.id` untuk
// tombol "Tandai Selesai" (BR-REV-06). Memilih kolom reviewer tanpa kunci
// relasinya membuat Eloquent tak bisa mencocokkan baris, dan `reviewer`
// diam-diam menjadi null.

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: User, 2: FormSubmission} */
function threadAuthorFixture(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);

    $author = User::factory()->create(['name' => 'Penilai Ulir']);
    $reviewer = Reviewer::factory()->create([
        'user_id' => $author->id,
        'reviewer_type' => 'internal',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perbaiki metodologi.',
    ]);

    return [$owner, $author, $submission];
}

it('mengirim penulis ulir ke layar pemilik pengajuan', function () {
    [$owner, $author, $submission] = threadAuthorFixture();

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.review_summaries.0.reviewer.user.id', $author->id)
            ->where('submission.review_summaries.0.reviewer.user.name', 'Penilai Ulir')
            ->where('submission.review_summaries.0.reviewer.reviewer_type', 'internal')
            ->etc());
});

it('mengirim penulis ulir ke layar Operator', function () {
    [, $author, $submission] = threadAuthorFixture();

    Permission::findOrCreate('users.manage', 'web');
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(['users.manage', 'submissions.view-all']);

    $this->actingAs($operator)
        ->get(route('admin.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.review_summaries.0.reviewer.user.id', $author->id)
            ->where('submission.review_summaries.0.reviewer.user.name', 'Penilai Ulir')
            ->where('submission.review_summaries.0.reviewer.reviewer_type', 'internal')
            ->etc());
});

it('mengirim penulis ulir ke layar reviewer yang menulisnya', function () {
    [, $author, $submission] = threadAuthorFixture();

    $this->actingAs($author)
        ->get(route('reviewer.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('myReviewSummary.reviewer.user.id', $author->id)
            ->where('myReviewSummary.reviewer.user.name', 'Penilai Ulir')
            ->where('myReviewSummary.reviewer.reviewer_type', 'internal')
            ->etc());
});
