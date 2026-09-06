<?php

// tests/Feature/Submission/StartSubmissionTest.php
//
// ADR 0012 — baris form_submissions lahir di langkah 1 supaya created_at
// menjadi jangkar Temporal Field Binding (BR-FE-11).

use App\Actions\FormSubmission\StartSubmission;
use App\Models\FormSubmission;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;

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
