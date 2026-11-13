<?php

// tests/Feature/Review/DecisionReadinessTest.php
//
// BR-REV-05 — "siap diputuskan" adalah keadaan turunan, bukan kolom. `run()` menjawab satu
// pengajuan untuk layar keputusan; `whereReady()` menjawab daftar dan dashboard. Keduanya harus
// menghasilkan himpunan yang sama, kalau tidak penyaring "Menunggu keputusan" berbohong.

use App\Models\FormSubmission;
use App\Queries\Review\DecisionReadiness;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('whereReady sama dengan run()->ready atas dataset campuran', function () {
    $ready = readyForDecision();
    $noReview = outcomeSubmission(Submitted::class, needsReview: false);

    $running = outcomeSubmission();
    reviewerOn($running, 'completed');

    $noReviewer = outcomeSubmission(Submitted::class);

    $revision = outcomeSubmission();
    $seat = reviewerOn($revision, 'completed');
    openSummaryOn($revision, $seat);
    finishSeat($seat);

    $approved = outcomeSubmission(Approved::class, needsReview: false);

    $fx = monevFixture();
    $report = FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'submitted_by' => $fx['owner']->id,
        'status' => Submitted::class,
    ]);

    $all = FormSubmission::query()->get();
    $byRun = $all->filter(fn (FormSubmission $s): bool => DecisionReadiness::run($s)->ready)->pluck('id')->sort()->values()->all();
    $bySql = DecisionReadiness::whereReady(FormSubmission::query())->pluck('id')->sort()->values()->all();

    expect($bySql)->toBe($byRun)
        ->and($byRun)->toBe(collect([$ready->id, $noReview->id])->sort()->values()->all())
        ->and($revision->fresh()->status)->toBeInstanceOf(NeedsRevision::class)
        ->and($byRun)->not->toContain($running->id, $noReviewer->id, $approved->id, $report->id);
});

it('kemajuan putaran dan penahan disebut berurutan', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a);
    finishSeat($a);

    $readiness = DecisionReadiness::run($submission);

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->approvable)->toBeFalse()
        ->and($readiness->openThreadCount)->toBe(1)
        ->and($readiness->blockers)->toBe([
            '1 dari 2 penilai belum menyelesaikan putaran.',
            '1 catatan penilai masih terbuka.',
        ])
        ->and($readiness->round?->kind)->toBe('assessment')
        ->and($readiness->round?->number)->toBe(1)
        ->and($readiness->round?->finished)->toBe(1)
        ->and($readiness->round?->total)->toBe(2);
});

it('pengajuan siap tanpa catatan bisa disetujui', function () {
    $readiness = DecisionReadiness::run(readyForDecision());

    expect($readiness->ready)->toBeTrue()
        ->and($readiness->approvable)->toBeTrue()
        ->and($readiness->blockers)->toBe([]);
});
