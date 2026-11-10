<?php

// tests/Feature/Migrations/BackfillReviewRoundsTest.php
//
// BR-REV-16, spec §4.10 — pengajuan yang sedang menunggu saat rilis mendapat satu putaran
// berjalan: putaran penilaian bila belum pernah direvisi (reviewer `completed` dihitung sudah
// Selesai), putaran pemeriksaan bila sedang diperiksa ulang. Tanpa notifikasi dan tanpa
// transisi status (keputusan rencana P1-W4).

use App\Enums\ReviewRoundKind;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewRound;
use App\Models\ReviewRoundParticipant;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Notification;

function runBackfillReviewRounds(): void
{
    $migration = require database_path('migrations/2026_11_11_200000_backfill_review_rounds.php');
    $migration->up();
}

/** Kursi lama: dibuat tanpa jalur produksi, seperti data sebelum putaran ada. */
function legacySeat(FormSubmission $submission, string $evaluation): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => User::factory()->create()->id])->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);
}

function legacySubmission(string $state = UnderReview::class, bool $resubmitted = false): FormSubmission
{
    $submission = FormSubmission::factory()->create(['status' => $state, 'is_submitted' => true]);

    if ($resubmitted) {
        AuditTrail::record('Pengajuan dikirim ulang setelah revisi', $submission, $submission->submittedBy);
    }

    return $submission;
}

function finishedAt(SubmissionReviewer $seat): mixed
{
    return ReviewRoundParticipant::query()->where('submission_reviewer_id', $seat->id)->sole()->finished_at;
}

beforeEach(function () {
    Notification::fake();
});

it('pengajuan yang belum pernah direvisi mendapat putaran penilaian; reviewer completed dihitung Selesai', function () {
    $submission = legacySubmission();
    $done = legacySeat($submission, 'completed');
    $pending = legacySeat($submission, 'pending');

    runBackfillReviewRounds();

    $round = $submission->runningReviewRound();
    expect($round->kind)->toBe(ReviewRoundKind::Assessment)
        ->and($round->number)->toBe(1)
        ->and($round->participants()->count())->toBe(2)
        ->and(finishedAt($done))->not->toBeNull()
        ->and(finishedAt($pending))->toBeNull()
        ->and($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('pengajuan yang dikirim ulang mendapat putaran pemeriksaan untuk pemilik catatan terbuka', function () {
    $submission = legacySubmission(resubmitted: true);
    $a = legacySeat($submission, 'completed');
    legacySeat($submission, 'completed');
    ReviewSummary::create([
        'form_submission_id' => $submission->id, 'reviewer_id' => $a->reviewer_id,
        'status' => 'open', 'summary_notes' => 'Perkuat metodologi.',
    ]);

    runBackfillReviewRounds();

    $round = $submission->runningReviewRound();
    expect($round->kind)->toBe(ReviewRoundKind::Recheck)
        ->and($round->participants()->pluck('submission_reviewer_id')->all())->toBe([$a->id])
        ->and(finishedAt($a))->toBeNull();
});

it('putaran pemeriksaan tanpa catatan terbuka langsung selesai siap, tanpa notifikasi', function () {
    $submission = legacySubmission(resubmitted: true);
    legacySeat($submission, 'completed');

    runBackfillReviewRounds();

    $round = $submission->latestReviewRound();
    expect($round->kind)->toBe(ReviewRoundKind::Recheck)
        ->and($round->isRunning())->toBeFalse()
        ->and($round->outcome)->toBe(ReviewRound::OUTCOME_READY)
        ->and($round->participants()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('laporan monev, pengajuan yang sudah diputuskan, dan pengajuan tanpa reviewer tidak diberi putaran', function () {
    $fx = monevFixture();
    $report = FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'submitted_by' => $fx['owner']->id,
        'status' => Submitted::class,
    ]);
    legacySeat($report, 'pending');
    legacySeat(legacySubmission(Approved::class), 'completed');
    legacySubmission(Submitted::class);

    runBackfillReviewRounds();

    expect(ReviewRound::count())->toBe(0);
});

it('aman dijalankan dua kali', function () {
    $submission = legacySubmission();
    legacySeat($submission, 'completed');
    legacySeat($submission, 'pending');

    runBackfillReviewRounds();
    runBackfillReviewRounds();

    expect(ReviewRound::count())->toBe(1)
        ->and(ReviewRoundParticipant::count())->toBe(2);
});
