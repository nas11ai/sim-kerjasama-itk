<?php

// tests/Unit/FormSubmissionPhaseDetailTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the phase detail it was created against', function () {
    $detail = FormPhaseDetail::factory()->create();
    // BR-MON-03 — submission harus terikat ke satu FormPhaseDetail lewat
    // kolom eksplisit, bukan tebakan lewat form_id (ADR 0004).
    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
    ]);

    expect($submission->formPhaseDetail->id)->toBe($detail->id);
});

it('does not guess a phase detail when the column is null', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => null]);

    expect($submission->formPhaseDetail)->toBeNull();
});
