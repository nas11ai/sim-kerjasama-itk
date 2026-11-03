<?php

// tests/Feature/Submission/StartSubmissionTest.php
//
// ADR 0012 — baris form_submissions lahir di langkah 1 supaya created_at
// menjadi jangkar Temporal Field Binding (BR-FE-11).

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('[SM-WIZARD-01] langkah 1 membuat draft beserta jangkar temporalnya', function () {
    $fx = wizardFixture();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->last_completed_step)->toBe(1)
        ->and($submission->is_submitted)->toBeFalse()
        ->and($submission->submission_period_id)->toBe($fx['period']->id)
        ->and($submission->form_phase_detail_id)->toBe($fx['detail']->id)
        // Jangkar temporal: created_at harus benar-benar tersimpan, bukan null.
        ->and($submission->created_at)->not->toBeNull();
});

it('memakai ulang draft yang sudah ada alih-alih membuat kembar', function () {
    $fx = wizardFixture();

    $first = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $second = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($second->id)->toBe($first->id)
        ->and(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(1);
});

it('[SM-WIZARD-12] pengajuan yang berakhir terminal tidak memblokir draft baru', function () {
    $fx = wizardFixture();

    $rejected = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Rejected::class,
        'is_submitted' => true,
    ]);

    $fresh = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($fresh->id)->not->toBe($rejected->id)
        ->and($fresh->status)->toBeInstanceOf(Draft::class)
        // Baris lama tidak disentuh — status terminal itu beku.
        ->and($rejected->fresh()->status)->toBeInstanceOf(Rejected::class);
});

it('memakai ulang pengajuan yang sedang direvisi, bukan membuat yang baru', function () {
    $fx = wizardFixture();

    $revising = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => NeedsRevision::class,
        'is_submitted' => true,
    ]);

    $resolved = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($resolved->id)->toBe($revising->id);
});

// BR-FE-01 — periode yang sudah ditutup tidak melahirkan pengajuan baru.
//
// Predikat "tertutup"-nya sengaja satu dengan yang dipakai BR-SM-13 untuk
// mengarsipkan draft (SubmissionPeriodGate). Dua salinan pasti menyimpang:
// yang satu akan ingat is_force_closed, yang lain tidak — dan persis itu yang
// terjadi pada isPeriodActive() warisan.

it('[FE-ACCESS-07] pengajuan tidak bisa dibuat saat periode ditutup paksa', function () {
    $fx = wizardFixture();
    $fx['period']->update(['is_force_closed' => true]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(HttpException::class)
        ->and(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(0);
});

it('[FE-ACCESS-07] jalur HTTP ikut menolak periode yang ditutup paksa', function () {
    $fx = wizardFixture();
    $fx['period']->update(['is_force_closed' => true]);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['phase']->id,
        ])
        ->assertForbidden();

    expect(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(0);
});

it('[FE-ACCESS-07] periode yang seluruh batasnya sudah lewat juga menolak', function () {
    $fx = wizardFixture();
    $fx['period']->submissionDates()->update(['datetime' => now()->subWeek()]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(HttpException::class);
});

it('[FE-ACCESS-08] pengajuan bisa dibuat saat periode masih berjalan', function () {
    $fx = wizardFixture();

    expect($fx['period']->is_force_closed)->toBeFalse();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->last_completed_step)->toBe(1);
});

it('[FE-ACCESS-08] batas yang sebagian masih di depan menjaga periode tetap terbuka', function () {
    $fx = wizardFixture();

    // Seluruh SubmissionDate adalah batas akhir, bukan jendela mulai–selesai.
    // Satu batas yang sudah lewat tidak menutup periode selama masih ada yang
    // di depan — predikat warisan yang membacanya sebagai jendela justru
    // menolak pengajuan sebelum batas pertama tiba.
    SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => now()->subDay(),
        'submission_period_id' => $fx['period']->id,
    ]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class);
});
