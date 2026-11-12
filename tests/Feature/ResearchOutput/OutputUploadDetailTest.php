<?php

// tests/Feature/ResearchOutput/OutputUploadDetailTest.php
//
// W139 — detail "Upload Luaran" (DDD monev Detail 7) dikenali dari tenggat berjenis `output`
// (Batas Akhir). Hanya detail itu yang menggerbangi luaran; ia tidak menerima pengajuan.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\SubmissionPeriodPhase;
use App\Queries\ResearchOutput\OutputUploadDetail;
use App\Queries\Submission\ResearcherStartOptions;
use Illuminate\Validation\ValidationException;

it('mengenali detail Upload Luaran induknya', function () {
    $fx = outputUploadFixture();

    expect(OutputUploadDetail::for($fx['submission'])?->id)->toBe($fx['outputDetail']->id);
});

it('tanpa detail Upload Luaran hasilnya null', function () {
    $fx = outputUploadFixture();
    $fx['outputDetail']->delete();

    expect(OutputUploadDetail::for($fx['submission']))->toBeNull();
});

it('detail Upload Luaran tahap lain atau sebelum detail induk tidak dihitung', function () {
    $fx = outputUploadFixture();
    $step = $fx['outputDetail']->only(['form_access_control_id', 'phase_type_id', 'deadline_type']);
    $fx['outputDetail']->delete();
    phaseStep($fx['phase'], [...$step, 'order' => 0]);

    $otherPhase = FormPhase::create(['title' => 'Tahap Lain', 'is_active' => true]);
    phaseStep($otherPhase, [...$step, 'order' => 9]);

    // BR-FE-32 — Upload Luaran versi lain tahap yang sama juga bukan milik induk.
    phaseVersion($fx['phase'], [[...$step, 'order' => 9]]);

    expect(OutputUploadDetail::for($fx['submission']))->toBeNull();
});

it('detail Upload Luaran tidak menerima pengajuan', function () {
    $fx = outputUploadFixture();
    // Tanpa tautan periode, ResearcherStartOptions tidak menawarkan apa pun dan assert-nya hampa.
    SubmissionPeriodPhase::firstOrCreate(['submission_period_id' => $fx['period']->id, 'form_phase_id' => $fx['phase']->id]);

    // Form tersendiri untuk detail Upload Luaran: kalau berbagi Form pengajuan, detail
    // pengajuan (order lebih kecil) selalu menang dan detail ini tak pernah ditawarkan.
    $form = Form::factory()->create();
    $control = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $fx['user']->userProfile->organization_id,
    ]);
    $step = [...$fx['outputDetail']->only(['phase_type_id', 'order', 'needs_review', 'deadline_type']), 'form_access_control_id' => $control->id];
    $fx['outputDetail']->delete();
    $fx['outputDetail'] = phaseStep($fx['phase'], $step);

    $offered = collect(ResearcherStartOptions::run($fx['user']))
        ->flatMap(fn ($period) => $period->forms)
        ->map(fn ($option) => $option->formId);

    expect($offered)->not->toContain($form->id)
        ->and($offered)->toContain($fx['form']->id);

    expect(fn () => StartSubmission::run($form->id, $fx['period']->id, $fx['outputDetail']->fresh(), $fx['user']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('form_phase_detail_id'));
});

it('tenggat Upload Luaran berlabel Batas Akhir', function () {
    $fx = outputUploadFixture();

    expect(stepDate($fx['outputDetail'], $fx['period'])->label())->toBe('Batas Akhir');
});
