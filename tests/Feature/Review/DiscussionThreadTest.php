<?php

// tests/Feature/Review/DiscussionThreadTest.php
//
// Ulir diskusi review. Yang menutup ulir adalah reviewer, bukan researcher —
// kalau researcher bisa menutup sendiri, ia menghapus permintaan revisi tanpa
// persetujuan siapa pun (BR-REV-06).
//
// BR-REV-16 — menutup ulir tidak memutuskan hasil; hasil dihitung di akhir putaran.

use App\Actions\Review\JoinReviewRound;
use App\Actions\Review\ResolveReviewSummary;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\RevisionResolvedNotification;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\NotificationChannels;
use App\Support\ReviewSummaryGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/** @return array{0: User, 1: User, 2: FormSubmission, 3: Reviewer, 4: SubmissionReviewer} */
function discussionFixture(string $evaluation = 'completed'): array
{
    $owner = User::factory()->create();
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);

    $reviewerUser = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $reviewerUser->id]);

    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);
    JoinReviewRound::run($assignment);

    return [$owner, $reviewerUser, $submission, $reviewer, $assignment];
}

function openThread(FormSubmission $submission, Reviewer $reviewer): ReviewSummary
{
    return ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi sampling.',
    ]);
}

it('[REV-DISCUSS-01] reviewer membuka ulir beserta komentar pertama', function () {
    [, $reviewerUser, $submission, $reviewer, $assignment] = discussionFixture();

    ReviewSummaryGuard::assertMayCreate($assignment);
    $summary = openThread($submission, $reviewer);

    ReviewComment::create([
        'review_summary_id' => $summary->id,
        'user_id' => $reviewerUser->id,
        'reviewer_id' => $reviewer->id,
        'comment_text' => 'Bagian 3 perlu pembanding.',
    ]);

    expect($summary->fresh()->status)->toBe('open')
        ->and($summary->reviewComments()->count())->toBe(1);
});

it('[REV-DISCUSS-02] researcher membalas di ulir yang sama', function () {
    [$owner, , $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);

    ReviewComment::create([
        'review_summary_id' => $summary->id,
        'user_id' => $owner->id,
        'comment_text' => 'Baik, kami tambahkan skenario pembanding.',
    ]);

    // Balasannya masuk ke ulir yang sama, bukan membuka ulir baru.
    expect($summary->fresh()->reviewComments()->count())->toBe(1);
});

it('[REV-DISCUSS-03] reviewer menandai ulir resolved', function () {
    [, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);

    ResolveReviewSummary::run($summary, $reviewerUser);

    expect($summary->fresh()->status)->toBe('resolved');
});

it('[REV-DISCUSS-04] researcher tidak bisa me-resolve ulirnya sendiri', function () {
    [$owner, , $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);

    // Kalau ini lolos, researcher bisa memaksa pengajuannya disetujui.
    ResolveReviewSummary::run($summary, $owner);
})->throws(AuthorizationException::class);

it('[REV-DISCUSS-05] ulir terakhir yang resolved tidak memutuskan hasil review', function () {
    [, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);

    ResolveReviewSummary::run($summary, $reviewerUser);

    // Seluruh evaluasi sudah masuk dan tidak ada ulir terbuka lagi, tapi hasil menunggu
    // Selesai menilai (BR-REV-16) — dan APPROVED hanya lewat Operator (BR-REV-05).
    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->runningReviewRound())->not->toBeNull();
});

it('[REV-DISCUSS-05] ulir lain yang masih terbuka tidak mengembalikan ke revisi', function () {
    [, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $first = openThread($submission, $reviewer);
    openThread($submission, $reviewer);

    ResolveReviewSummary::run($first, $reviewerUser);

    // Celah 3 ADR 0018: menutup satu ulir saat ulir lain terbuka tidak memicu NEEDS_REVISION.
    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[BR-NOT-02] ulir yang ditutup memberi tahu peneliti utama lewat in-app saja', function () {
    Notification::fake();
    [$owner, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);

    ResolveReviewSummary::run($summary, $reviewerUser);

    Notification::assertSentTo(
        $owner,
        RevisionResolvedNotification::class,
        fn (RevisionResolvedNotification $n, array $channels) => $channels === [NotificationChannels::IN_APP],
    );
    Notification::assertNotSentTo($reviewerUser, RevisionResolvedNotification::class);
});

it('menutup ulir yang sudah resolved tidak mengulang efek sampingnya', function () {
    [, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);
    ResolveReviewSummary::run($summary, $reviewerUser);

    Notification::fake();
    ResolveReviewSummary::run($summary->fresh(), $reviewerUser);

    expect(Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $summary->getMorphClass())
        ->where('subject_id', $summary->id)
        ->where('description', 'Diskusi review ditutup')
        ->count())->toBe(1);
    Notification::assertNothingSent();
});
