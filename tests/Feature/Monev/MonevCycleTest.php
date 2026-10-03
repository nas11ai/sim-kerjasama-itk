<?php

// tests/Feature/Monev/MonevCycleTest.php
//
// BR-MON-01/02/03/08 — siklus monev sebagai FormPhaseDetail berurutan dalam
// FormPhase yang sama, dengan tiap laporannya child FormSubmission.
//
// `canProceed()` adalah turunan keadaan, bukan kolom (register #13). Versi
// warisan memeriksa `status instanceof Approved` beserta persetujuan seluruh
// reviewer — pertanyaan yang berbeda, dan yang membuat setiap laporan monev
// terkunci selamanya karena laporan memang tidak pernah berstatus APPROVED.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Override\GrantPhaseOverride;
use App\Data\Request\PhaseOverrideInput;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use App\Support\MonevAccessGate;
use App\Support\PhaseAccessGate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

it('[MON-CYCLE-01] fase monev tertutup selama induk belum APPROVED', function () {
    $fx = monevFixture(parentStatus: UnderReview::class);

    expect(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev1']))->toBeFalse()
        ->and(fn () => StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']))
        ->toThrow(ValidationException::class);
});

it('[MON-CYCLE-02] induk APPROVED membuka fase Laporan Monev I', function () {
    $fx = monevFixture();

    expect(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev1']))->toBeTrue();

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    expect($laporan->parent_submission_id)->toBe($fx['parent']->id);
});

it('[MON-CYCLE-03] Monev II tertutup selama Monev I belum disubmit', function () {
    $fx = monevFixture();

    StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    // Laporannya ada tapi belum dikirim — tahap itu belum selesai.
    expect(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev2']))->toBeFalse();
});

it('[MON-CYCLE-03] Monev II juga tertutup saat Monev I belum dibuat sama sekali', function () {
    $fx = monevFixture();

    // Belum pernah ada laporan sama sekali menghalangi dengan alasan yang sama
    // seperti laporan yang belum disubmit: tahap itu belum selesai.
    expect(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev2']))->toBeFalse();
});

it('[MON-CYCLE-04] Monev I yang tuntas membuka Monev II', function () {
    $fx = monevFixture(monev1NeedsReview: true);

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $laporan->update(['is_submitted' => true]);

    $reviewer = Reviewer::factory()->create(['user_id' => verifiedResearcher()->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $laporan->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'active',
        'evaluation_status' => 'completed',
    ]);

    expect($laporan->fresh()->canProceed())->toBeTrue()
        ->and(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev2']))->toBeTrue();
});

it('[MON-CYCLE-04] ReviewSummary yang masih open menahan siklus berikutnya', function () {
    $fx = monevFixture(monev1NeedsReview: true);

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $laporan->update(['is_submitted' => true]);

    $reviewer = Reviewer::factory()->create(['user_id' => verifiedResearcher()->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $laporan->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'active',
        'evaluation_status' => 'completed',
    ]);

    ReviewSummary::create([
        'form_submission_id' => $laporan->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perlu perbaikan metode.',
    ]);

    expect($laporan->fresh()->canProceed())->toBeFalse()
        ->and(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev2']))->toBeFalse();
});

it('[MON-CYCLE-05] detail tanpa review cukup disubmit', function () {
    $fx = monevFixture(monev1NeedsReview: false);

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $laporan->update(['is_submitted' => true]);

    // Menunggu evaluasi yang memang tidak ada akan mengunci siklus berikutnya
    // selamanya tanpa ada yang salah.
    expect($laporan->fresh()->canProceed())->toBeTrue()
        ->and(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev2']))->toBeTrue();
});

it('[MON-CYCLE-06] satu induk hanya punya satu laporan per detail', function () {
    $fx = monevFixture();

    $pertama = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $kedua = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    expect($kedua->id)->toBe($pertama->id)
        ->and(FormSubmission::where('parent_submission_id', $fx['parent']->id)->count())->toBe(1);
});

it('[MON-CYCLE-07] batas laporan berlaku per induk, bukan per peneliti', function () {
    $fx = monevFixture();

    // Induk kedua milik peneliti yang sama — BR-SM-01 mengizinkan dua skema
    // berbeda dalam satu periode, jadi bacaan "satu laporan per peneliti" akan
    // memblokir yang kedua tanpa dasar.
    $indukLain = FormSubmission::factory()->create([
        'submitted_by' => $fx['owner']->id,
        'form_phase_detail_id' => $fx['pengajuan']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $kedua = StartChildSubmission::run($indukLain, $fx['monev1'], $fx['owner']);

    expect($kedua->parent_submission_id)->toBe($indukLain->id)
        ->and(FormSubmission::whereNotNull('parent_submission_id')->count())->toBe(2);
});

it('[MON-CYCLE-10] penarikan membekukan siklus monev berjalan', function () {
    $fx = monevFixture();

    $fx['parent']->status->transitionTo(Withdrawn::class);

    expect(fn () => StartChildSubmission::run($fx['parent']->fresh(), $fx['monev2'], $fx['owner']))
        ->toThrow(ValidationException::class);
});

it('[MON-CYCLE-11] laporan yang terlanjur terkirim tetap terbaca', function () {
    $fx = monevFixture();

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $laporan->update(['is_submitted' => true]);

    $fx['parent']->status->transitionTo(Withdrawn::class);

    expect(FormSubmission::find($laporan->id))->not->toBeNull()
        ->and($laporan->fresh()->is_submitted)->toBeTrue();
});

it('[MON-CYCLE-12] siklus yang dibekukan tidak bisa dibuka lagi', function () {
    $fx = monevFixture();

    $fx['parent']->status->transitionTo(Withdrawn::class);

    // WITHDRAWN terminal — state machine dijaga asiklik.
    expect(fn () => $fx['parent']->fresh()->status->transitionTo(Approved::class))
        ->toThrow(Exception::class);
});

// BR-MON-05 — penerapan BR-FE-08 pada fase monev. Aturannya tidak ditulis
// ulang: PhaseAccessGate yang sama menjaga keduanya, dan dua salinan pasti
// menyimpang. Yang diuji di sini adalah bahwa ia benar-benar berlaku di sini.

it('[MON-CYCLE-08] lewat batas monev tanpa override, pengisian ditolak', function () {
    $this->travelTo('2026-06-10 09:00:00');
    $fx = monevFixture();

    $fx['monev1']->submissionDate->update(['datetime' => '2026-06-01 23:59:00']);

    $laporan = FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'submitted_by' => $fx['owner']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'is_submitted' => false,
    ]);

    expect(PhaseAccessGate::isOpenFor($laporan, $fx['monev1']->fresh()))->toBeFalse();
});

it('[MON-CYCLE-09] override yang masih berlaku membuka kembali fase monev', function () {
    $this->travelTo('2026-06-10 09:00:00');
    $fx = monevFixture();

    $fx['monev1']->submissionDate->update(['datetime' => '2026-06-01 23:59:00']);

    $laporan = FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'submitted_by' => $fx['owner']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'is_submitted' => false,
    ]);

    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = verifiedResearcher();
    $operator->givePermissionTo('submissions.view-all');

    GrantPhaseOverride::run(
        $laporan,
        $fx['monev1']->fresh(),
        $operator,
        new PhaseOverrideInput('Kendala jaringan saat batas monev berjalan', '2026-06-20 23:59:00'),
    );

    expect(PhaseAccessGate::isOpenFor($laporan, $fx['monev1']->fresh()))->toBeTrue();
});
