<?php

// tests/Feature/Review/DiscussionNotificationTest.php
//
// Ulir yang dibuka dan komentar yang masuk memberi tahu semua pihak yang
// membaca diskusinya — operator, reviewer aktif, pengaju — kecuali orang yang
// baru saja menulisnya. Reviewer yang sudah diganti tidak lagi termasuk
// (BR-SM-07, BR-REV-10).

use App\Actions\Review\AddReviewComment;
use App\Actions\Review\JoinReviewRound;
use App\Actions\Review\OpenReviewThread;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewCommentAddedNotification;
use App\Notifications\ReviewThreadOpenedNotification;
use App\Queries\Review\DiscussionParticipants;
use App\States\Submission\UnderReview;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** @return array{owner: User, reviewer: User, assignment: SubmissionReviewer, operator: User, replaced: User} */
function discussionAudience(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'form_phase_detail_id' => FormPhaseDetail::factory()->create(['needs_review' => true])->id,
        'status' => UnderReview::class,
    ]);

    $reviewer = User::factory()->create();
    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $reviewer->id])->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);
    // BR-REV-16 — seperti AssignReviewer: kursi baru ikut putaran penilaian (BR-REV-17).
    JoinReviewRound::run($assignment);

    $replaced = User::factory()->create();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $replaced->id])->id,
        'evaluation_status' => 'completed',
        'status' => 'replaced',
    ]);

    // Lewat role, bukan permission langsung: begitulah operator sungguhan memegangnya.
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create()->assignRole(
        Role::findOrCreate('operator', 'web')->givePermissionTo('submissions.view-all'),
    );

    return compact('owner', 'reviewer', 'assignment', 'operator', 'replaced');
}

it('mengumpulkan operator, reviewer aktif, dan pengaju tanpa reviewer yang sudah diganti', function () {
    ['owner' => $owner, 'reviewer' => $reviewer, 'assignment' => $assignment, 'operator' => $operator] = discussionAudience();
    User::factory()->create();

    $ids = DiscussionParticipants::run($assignment->formSubmission)->modelKeys();

    expect($ids)->toEqualCanonicalizing([$owner->id, $reviewer->id, $operator->id]);
});

it('mengecualikan penulisnya dari penerima', function () {
    ['owner' => $owner, 'assignment' => $assignment, 'operator' => $operator] = discussionAudience();

    $ids = DiscussionParticipants::run($assignment->formSubmission, $owner->id)->modelKeys();

    expect($ids)->not->toContain($owner->id)->toContain($operator->id);
});

it('ulir yang dibuka memberi tahu peserta diskusi selain pembukanya', function () {
    Notification::fake();
    ['owner' => $owner, 'reviewer' => $reviewer, 'assignment' => $assignment, 'operator' => $operator, 'replaced' => $replaced] = discussionAudience();

    OpenReviewThread::run($assignment->formSubmission, $assignment, 'Perkuat metodologi.');

    Notification::assertSentTo([$owner, $operator], ReviewThreadOpenedNotification::class);
    Notification::assertNotSentTo([$reviewer, $replaced], ReviewThreadOpenedNotification::class);
});

it('komentar baru memberi tahu peserta diskusi selain penulisnya', function () {
    Notification::fake();
    ['owner' => $owner, 'reviewer' => $reviewer, 'assignment' => $assignment, 'operator' => $operator, 'replaced' => $replaced] = discussionAudience();
    $summary = ReviewSummary::create([
        'form_submission_id' => $assignment->form_submission_id,
        'reviewer_id' => $assignment->reviewer_id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);

    AddReviewComment::run($summary, $owner, 'Sudah kami perbaiki.');

    Notification::assertSentTo([$reviewer, $operator], ReviewCommentAddedNotification::class);
    Notification::assertNotSentTo([$owner, $replaced], ReviewCommentAddedNotification::class);
});

it('[BR-NOT-01] isi in-app menunjuk pengajuan dan ulirnya', function () {
    ['owner' => $owner, 'assignment' => $assignment] = discussionAudience();
    $summary = ReviewSummary::create([
        'form_submission_id' => $assignment->form_submission_id,
        'reviewer_id' => $assignment->reviewer_id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);

    $payload = (new ReviewThreadOpenedNotification($summary))->toArray($owner);

    expect($payload)->toMatchArray([
        'type' => 'review_thread_opened',
        'form_submission_id' => $assignment->form_submission_id,
        'review_summary_id' => $summary->id,
    ]);
});
