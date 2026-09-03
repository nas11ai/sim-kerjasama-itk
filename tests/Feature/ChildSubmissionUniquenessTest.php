<?php

// tests/Feature/ChildSubmissionUniquenessTest.php
//
// I3 — unique index `one_child_submission_per_phase_detail` (BR-MON-03)
// belum pernah diuji sama sekali (`grep -rn parent_submission_id tests/`
// kosong sebelum file ini ada). Index-nya per PARENT submission, bukan per
// researcher — lihat amandemen BR-MON-03 di
// docs/requirement-analysis/ddd/supporting/02_monev.md.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('blocks a second child submission under the same parent for the same phase detail', function () {
    $detail = FormPhaseDetail::factory()->create();
    $parent = FormSubmission::factory()->create();

    FormSubmission::factory()->create([
        'parent_submission_id' => $parent->id,
        'form_phase_detail_id' => $detail->id,
    ]);

    FormSubmission::factory()->create([
        'parent_submission_id' => $parent->id,
        'form_phase_detail_id' => $detail->id,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('allows two different parents to each have a child submission for the same phase detail', function () {
    // BR-MON-03 (diamandemen) — satu researcher boleh punya dua proposal
    // APPROVED sekaligus (BR-SM-01 mengizinkan dua skema berbeda dalam satu
    // period), dan masing-masing proposal wajib mengisi laporan monev-nya
    // sendiri untuk phase detail yang sama. Index per parent submission,
    // bukan per researcher, supaya skenario ini tidak salah diblokir.
    $detail = FormPhaseDetail::factory()->create();
    $researcher = User::factory()->create();

    $parentA = FormSubmission::factory()->create(['submitted_by' => $researcher->id]);
    $parentB = FormSubmission::factory()->create(['submitted_by' => $researcher->id]);

    FormSubmission::factory()->create([
        'parent_submission_id' => $parentA->id,
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => $researcher->id,
    ]);

    FormSubmission::factory()->create([
        'parent_submission_id' => $parentB->id,
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => $researcher->id,
    ]);

    expect(FormSubmission::where('form_phase_detail_id', $detail->id)->count())->toBe(2);
});
