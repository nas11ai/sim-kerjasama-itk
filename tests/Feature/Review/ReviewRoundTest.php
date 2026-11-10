<?php

// tests/Feature/Review/ReviewRoundTest.php
//
// BR-REV-16 — putaran review milik pengajuan utama. Putaran penilaian dibuka saat reviewer
// ditugaskan; putaran pemeriksaan dibuka saat peneliti mengirim ulang, hanya untuk reviewer
// yang catatannya masih terbuka. BR-REV-10 — pengganti mengambil kursi yang diganti sebagai
// peserta yang belum selesai. Laporan monev tidak pernah punya putaran.

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Actions\Monev\AssignCycleReviewer;
use App\Actions\Review\AssignReviewer;
use App\Actions\Review\ReplaceReviewer;
use App\Enums\ReviewRoundKind;
use App\Models\Reviewer;
use App\Models\ReviewRound;
use App\Models\ReviewRoundParticipant;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\RecheckRequestedNotification;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

function roundReviewer(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

function seatOf(SubmissionReviewer $seat): ?ReviewRoundParticipant
{
    return ReviewRoundParticipant::query()->where('submission_reviewer_id', $seat->id)->first();
}

it('penugasan pertama membuka putaran penilaian berisi reviewer itu', function () {
    $submission = outcomeSubmission(Submitted::class);

    $seat = AssignReviewer::run($submission, roundReviewer());

    $round = $submission->latestReviewRound();
    expect($round->kind)->toBe(ReviewRoundKind::Assessment)
        ->and($round->number)->toBe(1)
        ->and($round->isRunning())->toBeTrue()
        ->and($round->participants()->pluck('submission_reviewer_id')->all())->toBe([$seat->id]);
});

it('penugasan berikutnya bergabung ke putaran penilaian yang berjalan', function () {
    $submission = outcomeSubmission(Submitted::class);

    AssignReviewer::run($submission, roundReviewer());
    AssignReviewer::run($submission, roundReviewer());

    expect($submission->reviewRounds()->count())->toBe(1)
        ->and($submission->latestReviewRound()->participants()->count())->toBe(2);
});

it('penugasan setelah putaran selesai membuka putaran penilaian baru', function () {
    $submission = outcomeSubmission(Submitted::class);
    AssignReviewer::run($submission, roundReviewer());
    $submission->latestReviewRound()->update(['completed_at' => now(), 'outcome' => 'ready']);

    $seat = AssignReviewer::run($submission, roundReviewer());

    $round = $submission->latestReviewRound();
    expect($round->number)->toBe(2)
        ->and($round->kind)->toBe(ReviewRoundKind::Assessment)
        ->and($round->isRunning())->toBeTrue()
        ->and($round->participants()->pluck('submission_reviewer_id')->all())->toBe([$seat->id]);
});

it('[REV-OUTCOME-16] kirim ulang membuka putaran pemeriksaan untuk reviewer bercatatan terbuka', function () {
    $submission = outcomeSubmission(NeedsRevision::class);
    $a = reviewerOn($submission, 'completed');
    $b = reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a);
    // Putaran penilaian sudah disimpulkan NEEDS_REVISION — keadaan saat peneliti mengirim ulang.
    $submission->latestReviewRound()->update(['completed_at' => now(), 'outcome' => 'needs_revision']);

    ResubmitFormSubmission::run($submission, $submission->submittedBy);

    $round = $submission->runningReviewRound();
    expect($round->kind)->toBe(ReviewRoundKind::Recheck)
        ->and($round->number)->toBe(2)
        ->and($round->participants()->pluck('submission_reviewer_id')->all())->toBe([$a->id]);
    Notification::assertSentTo($a->reviewer->user, RecheckRequestedNotification::class);
    Notification::assertNotSentTo($b->reviewer->user, RecheckRequestedNotification::class);
});

it('[REV-OUTCOME-19] pengganti menjadi peserta belum selesai', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    seatOf($a)->update(['finished_at' => now()]);
    $c = roundReviewer();

    $incoming = ReplaceReviewer::run($submission, $a->reviewer, $c, null);

    $round = $submission->runningReviewRound();
    expect($round)->not->toBeNull()
        ->and($round->participants()->pluck('submission_reviewer_id')->all())->toBe([$incoming->id])
        ->and(seatOf($incoming)->finished_at)->toBeNull()
        ->and(seatOf($a))->toBeNull();
});

it('pengganti di putaran pemeriksaan mengambil kursi pemilik catatan', function () {
    $submission = outcomeSubmission(NeedsRevision::class);
    $a = reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a);
    $submission->latestReviewRound()->update(['completed_at' => now(), 'outcome' => 'needs_revision']);
    ResubmitFormSubmission::run($submission, $submission->submittedBy);

    $incoming = ReplaceReviewer::run($submission, $a->reviewer, roundReviewer(), null);

    expect($submission->runningReviewRound()->participants()->pluck('submission_reviewer_id')->all())
        ->toBe([$incoming->id]);
});

it('penugasan reviewer siklus monev tidak membuat putaran', function () {
    $this->seed(PermissionSeeder::class);
    $fx = monevFixture();
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $reviewer = Reviewer::factory()->create([
        'user_id' => verifiedResearcher()->id,
        'reviewer_type' => 'internal',
        'start_date' => now()->subYear(),
        'end_date' => now()->addYear(),
    ]);

    AssignCycleReviewer::run($fx['parent'], $fx['monev1'], $reviewer, $operator);

    expect(ReviewRound::count())->toBe(0);
});
