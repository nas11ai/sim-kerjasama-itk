<?php

// tests/Feature/Review/ReviewerSummaryPageTest.php
//
// REVIEWER-03-04 — kontrak props `Review/SummaryPage`: ulir milik penugasan, syarat membuka
// ulir (BR-FE-06), skor rekan (BR-REV-11), dan hitungan syarat keputusan (BR-REV-05/06).

use App\Actions\Review\ReplaceReviewer;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create(['name' => 'Dr. Budi Santoso'])->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);
    $this->user = User::factory()->create(['name' => 'Sari']);
    $this->reviewer = summaryPageReviewer($this->user);
    $this->assignment = summaryPageAssign($this->submission, $this->reviewer, 'completed');
});

function summaryPageReviewer(?User $user = null, string $type = 'internal'): Reviewer
{
    $user ??= User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));

    return Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => $type,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

function summaryPageAssign(FormSubmission $submission, Reviewer $reviewer, string $evaluation): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);
}

function summaryPageThread(FormSubmission $submission, Reviewer $reviewer, string $status = 'open'): ReviewSummary
{
    return ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => $status,
        'summary_notes' => 'Catatan '.$status,
    ]);
}

it('[SM-ACCESS-05] reviewer yang ditugaskan membuka Review/SummaryPage dengan ulirnya', function () {
    $thread = summaryPageThread($this->submission, $this->reviewer);

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/SummaryPage')
            ->where('submission.leadName', 'Dr. Budi Santoso')
            ->has('threads', 1)
            ->where('threads.0.id', $thread->id)
            ->where('threads.0.isOpen', true)
            ->where('canCreateThread', true)
            // BR-REV-13 — needs_revision/rejected bukan status ulir; layar tak menawarkannya.
            ->missing('availableStatuses'));
});

it('[REV-EVAL-04] evaluasi pending menutup pembuatan ringkasan', function () {
    $this->assignment->update(['evaluation_status' => 'pending']);

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page->where('canCreateThread', false)->etc());
});

it('[REV-EVAL-06] not_required membuka pembuatan ringkasan', function () {
    $this->assignment->update(['evaluation_status' => 'not_required']);

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page->where('canCreateThread', true)->etc());
});

it('[REV-DISCUSS-06] ulir reviewer yang digantikan tampil bagi penggantinya dengan penulis asli', function () {
    $thread = summaryPageThread($this->submission, $this->reviewer);
    $incomingUser = User::factory()->create(['name' => 'Andi']);
    ReplaceReviewer::run($this->submission, $this->reviewer, summaryPageReviewer($incomingUser), null);

    $this->actingAs($incomingUser)
        ->post(route('review-summaries.resolve', $thread))
        ->assertRedirect();

    $this->actingAs($incomingUser)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads', 1)
            ->where('threads.0.id', $thread->id)
            ->where('threads.0.isOpen', false)
            ->where('threads.0.reviewer', 'Sari')
            ->where('threads.0.inheritedBy', 'Andi')
            ->etc());
});

it('ulir yang tidak diwarisi ber-inheritedBy null', function () {
    summaryPageThread($this->submission, $this->reviewer);

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.reviewer', 'Sari')
            ->where('threads.0.inheritedBy', null)
            ->etc());
});

it('ulir reviewer lain tidak dikirim', function () {
    summaryPageThread($this->submission, $this->reviewer);
    $peer = summaryPageReviewer();
    summaryPageAssign($this->submission, $peer, 'completed');
    summaryPageThread($this->submission, $peer);

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1)->etc());
});

it('[REV-VIS-02] reviewer eksternal tidak menerima skor rekan meski semua selesai', function () {
    $externalUser = User::factory()->create();
    summaryPageAssign($this->submission, summaryPageReviewer($externalUser, 'external'), 'completed');

    $this->actingAs($externalUser)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scores.visible', false)
            ->where('scores.scores', [])
            ->etc());
});

it('[REV-VIS-03] skor rekan tertutup selama ada yang belum selesai', function () {
    summaryPageAssign($this->submission, summaryPageReviewer(), 'pending');

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page->where('scores.visible', false)->etc());
});

it('[REV-OUTCOME-02] syarat keputusan menghitung reviewer yang belum selesai', function () {
    $peer = summaryPageReviewer();
    summaryPageAssign($this->submission, $peer, 'pending');
    summaryPageThread($this->submission, $peer);
    summaryPageThread($this->submission, $this->reviewer, 'resolved');

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('outcome.reviewerCount', 2)
            ->where('outcome.unsettledCount', 1)
            ->where('outcome.openThreadCount', 1)
            ->etc());
});

it('[SM-ACCESS-06] reviewer yang digantikan ditolak di halaman ringkasan', function () {
    ReplaceReviewer::run($this->submission, $this->reviewer, summaryPageReviewer(), null);

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.summary', $this->submission))
        ->assertForbidden();
});
