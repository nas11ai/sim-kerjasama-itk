<?php

// tests/Feature/Submission/WizardResolutionHttpTest.php
//
// Endpoint langkah 1 (ADR 0012) dan pembedaan 409/403 yang dituntut
// [SM-STATE-04].

use App\Models\FormSubmission;
use App\States\Submission\Draft;

it('membuat draft lewat endpoint langkah 1', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['detail']->form_phase_id,
        ])
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->submitted_by)->toBe($fx['user']->id);
});

it('memanggil endpoint langkah 1 dua kali tidak melahirkan draft kembar', function () {
    $fx = wizardFixture();

    $payload = [
        'form_id' => $fx['form']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_id' => $fx['detail']->form_phase_id,
    ];

    $this->actingAs($fx['user'])->post(route('user.submissions.store'), $payload);
    $this->actingAs($fx['user'])->post(route('user.submissions.store'), $payload);

    expect(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(1);
});
