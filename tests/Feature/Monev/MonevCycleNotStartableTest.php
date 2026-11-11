<?php

// tests/Feature/Monev/MonevCycleNotStartableTest.php
//
// BR-MON-01 — laporan monev hanya lahir sebagai anak pengajuan APPROVED (StartChildSubmission).
// Detail siklus monev (Batas Submit bersiklus) bukan pintu pengajuan baru: bila Form-nya
// terbuka bagi peneliti, ia tidak boleh ditawarkan maupun diterima sebagai pengajuan tanpa induk.

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionPeriodPhase;
use App\Queries\Submission\AccessibleFormPhaseDetail;
use App\Queries\Submission\ResearcherStartOptions;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function monevCycleStartFixture(): array
{
    $fx = wizardFixture();
    // Tanpa tautan periode, ResearcherStartOptions tidak menawarkan apa pun dan assert-nya hampa.
    SubmissionPeriodPhase::firstOrCreate(['submission_period_id' => $fx['period']->id, 'form_phase_id' => $fx['phase']->id]);

    // Form tersendiri untuk laporan monev: kalau berbagi Form pengajuan, detail pengajuan
    // (order lebih kecil) selalu menang dan detail siklus tak pernah ditawarkan.
    $form = Form::factory()->create();
    $control = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $fx['user']->userProfile->organization_id,
    ]);

    calendarDate($fx['period'], DeadlineType::Submit, now()->addMonths(6), cycle: 1);
    $cycleDetail = FormPhaseDetail::create([
        'form_phase_id' => $fx['phase']->id,
        'form_access_control_id' => $control->id,
        'phase_type_id' => $fx['detail']->phase_type_id,
        'order' => $fx['detail']->order + 2,
        'needs_review' => true,
        'deadline_type' => DeadlineType::Submit,
        'deadline_cycle' => 1,
    ]);

    return [...$fx, 'monevForm' => $form, 'cycleDetail' => $cycleDetail];
}

it('Form laporan monev tidak ditawarkan sebagai pengajuan baru', function () {
    $fx = monevCycleStartFixture();

    $offered = collect(ResearcherStartOptions::run($fx['user']))
        ->flatMap(fn ($period) => $period->forms)
        ->map(fn ($option) => $option->formId);

    expect($offered)->not->toContain($fx['monevForm']->id)
        ->and($offered)->toContain($fx['form']->id);
});

it('detail siklus monev tidak menerima pengajuan baru', function () {
    $fx = monevCycleStartFixture();

    expect(fn () => StartSubmission::run($fx['monevForm']->id, $fx['period']->id, $fx['cycleDetail']->fresh(), $fx['user']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('form_phase_detail_id'));
});

it('langkah 1 wizard tidak memilih detail siklus monev', function () {
    $fx = monevCycleStartFixture();

    expect(AccessibleFormPhaseDetail::run($fx['phase']->id, $fx['monevForm']->id, $fx['user']))
        ->toBeNull();
});
