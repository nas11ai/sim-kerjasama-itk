<?php

// tests/Feature/Submission/SubmissionViewPagesTest.php
//
// Filter periode daftar peneliti. Periode sebuah pengajuan adalah FK-nya
// sendiri (ADR 0004), bukan pivot fase yang boleh dimiliki banyak periode.
// Daftar Operator diuji OperatorSubmissionListTest.

use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('menyaring pengajuan peneliti per periode dari periode milik pengajuan', function () {
    $fixture = wizardFixture();
    $other = SubmissionPeriod::factory()->create();

    foreach ([$fixture['period'], $other] as $period) {
        DB::table('submission_period_phases')->insert([
            'submission_period_id' => $period->id,
            'form_phase_id' => $fixture['phase']->id,
        ]);
    }

    $submission = FormSubmission::factory()->create([
        'form_id' => $fixture['form']->id,
        'form_phase_detail_id' => $fixture['detail']->id,
        'submission_period_id' => $fixture['period']->id,
        'submitted_by' => $fixture['user']->id,
    ]);

    // Filter periode di daftar memakai FK yang sama, bukan pivot fase yang dipakai dua periode.
    $this->actingAs($fixture['user'])
        ->get(route('user.submissions.index', ['period' => $fixture['period']->id]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.data.0.id', $submission->id));

    $this->actingAs($fixture['user'])
        ->get(route('user.submissions.index', ['period' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
});
