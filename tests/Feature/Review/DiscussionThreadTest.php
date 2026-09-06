<?php

// tests/Feature/Review/DiscussionThreadTest.php
//
// Ulir diskusi review. Yang menutup ulir adalah reviewer, bukan researcher —
// kalau researcher bisa menutup sendiri, ia bisa memaksa pengajuannya
// APPROVED tanpa persetujuan siapa pun (BR-REV-06).
//
// Menutup ulir terakhir memicu evaluasi ulang: kalau seluruh evaluasi sudah
// masuk, saat itu juga pengajuan berpindah tanpa aksi tambahan.

use App\Actions\Review\ResolveReviewSummary;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;
use App\Support\ReviewSummaryGuard;
use Illuminate\Auth\Access\AuthorizationException;

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

it('[REV-DISCUSS-05] ulir terakhir yang resolved memicu evaluasi ulang', function () {
    [, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $summary = openThread($submission, $reviewer);

    ResolveReviewSummary::run($summary, $reviewerUser);

    // Seluruh evaluasi sudah masuk dan tidak ada ulir terbuka lagi —
    // BR-REV-05 terpenuhi saat itu juga.
    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-DISCUSS-05] ulir lain yang masih terbuka mengarahkan ke revisi', function () {
    [, $reviewerUser, $submission, $reviewer] = discussionFixture();
    $first = openThread($submission, $reviewer);
    openThread($submission, $reviewer);

    ResolveReviewSummary::run($first, $reviewerUser);

    // Bukan tetap UNDER_REVIEW: seluruh evaluasi sudah masuk, jadi BR-REV-06
    // berlaku — ada ulir terbuka berarti NEEDS_REVISION, bukan menggantung.
    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class);
});
