<?php

// tests/Feature/FormEngine/FormBuilderWizardTest.php
//
// Wizard enam langkah (`admin.form-builder.*`). BR-FE-07 + BR-FE-26 (K8g) — wizard hanya
// memilih periode yang sudah ada (dibuat di layar Periode) lalu mengikat Batas Submit dan,
// bila tahapnya direview, Batas Evaluasi siklus pengajuan utamanya ke setiap detail baru.

use App\Enums\DeadlineType;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $faculty = Organization::create(['name' => 'Fakultas Wizard', 'type' => 'faculty']);
    $this->studyProgram = Organization::create([
        'name' => 'Prodi Wizard', 'type' => 'study_program', 'parent_id' => $faculty->id,
    ]);
    $this->formType = FormType::factory()->create();
    $this->fieldType = FieldType::create(['name' => 'text']);
    $this->phaseType = PhaseType::create(['name' => 'Tipe Tahap Wizard']);
});

/** @return array<string, mixed> */
function wizardPayload(SubmissionPeriod $period): array
{
    $fieldTypeId = test()->fieldType->id;

    return [
        'form' => [
            'title' => 'Form Wizard',
            'description' => null,
            'form_type_id' => test()->formType->id,
            'is_active' => true,
            'fields' => [[
                'field_type_id' => $fieldTypeId, 'label' => 'Judul', 'is_required' => true,
                'options' => [['label' => 'Pilihan A']],
            ]],
        ],
        'access_controls' => [
            ['permission' => 'submissions.create', 'study_program_id' => test()->studyProgram->id],
        ],
        'phase' => [
            'use_existing' => false,
            'existing_phase_id' => null,
            'new_phase_title' => 'Tahap Wizard',
            'new_phase_description' => null,
            'phase_type_id' => test()->phaseType->id,
            'needs_review' => true,
        ],
        'evaluation_forms' => [[
            'title' => 'Penilaian', 'description' => null, 'is_required' => true,
            'fields' => [[
                'field_type_id' => $fieldTypeId, 'label' => 'Skor', 'description' => null,
                'is_required' => true, 'validation_rules' => [],
                'options' => [['label' => 'Baik', 'value' => 'baik']],
            ]],
        ]],
        'submission_period' => ['existing_period_id' => $period->id],
    ];
}

/**
 * Periode berjalan dengan tenggat siklus pengajuan utama per jenis (BR-FE-25).
 *
 * @param  list<DeadlineType>  $types
 * @param  array<string, mixed>  $attributes
 */
function wizardPeriod(array $types = [DeadlineType::Submit, DeadlineType::Evaluation], array $attributes = [], string $when = '+1 month'): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create($attributes);

    foreach ($types as $index => $type) {
        SubmissionDate::create([
            'submission_period_id' => $period->id,
            'type' => $type,
            'datetime' => now()->modify($when)->addDays($index)->startOfMinute(),
        ]);
    }

    return $period;
}

function wizardDate(SubmissionPeriod $period, DeadlineType $type): SubmissionDate
{
    return $period->submissionDates()->ofType($type)->sole();
}

it('binds the period Batas Submit and Batas Evaluasi to the new details (BR-FE-26)', function () {
    $period = wizardPeriod();

    $response = $this->actingAs($this->admin)->post(route('admin.form-builder.store'), wizardPayload($period));

    $form = Form::query()->where('title', 'Form Wizard')->firstOrFail();
    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.forms.show', $form));

    $phase = FormPhase::query()->where('title', 'Tahap Wizard')->firstOrFail();
    $detail = FormPhaseDetail::query()->where('form_phase_id', $phase->id)->sole();

    expect($form->formFields()->sole()->formFieldOptions()->count())->toBe(1)
        ->and($form->formAccessControls()->sole()->organization_id)->toBe($this->studyProgram->id)
        ->and($detail->submission_date_id)->toBe(wizardDate($period, DeadlineType::Submit)->id)
        ->and($detail->evaluation_date_id)->toBe(wizardDate($period, DeadlineType::Evaluation)->id)
        ->and($detail->revision_date_id)->toBeNull()
        ->and(SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->count())->toBe(1);

    // BR-FE-17 — tenggatnya milik periode yang memakai tahap itu.
    expect(SubmissionDate::query()->selectableFor($phase)->pluck('id')->all())->toContain($detail->submission_date_id);

    $evaluation = ReviewEvaluationForm::query()->where('form_phase_detail_id', $detail->id)->sole();
    expect($evaluation->reviewFormFields()->sole()->reviewFormFieldOptions()->count())->toBe(1);
});

it('rejects a reviewed phase when the period has no Batas Evaluasi and saves nothing (BR-FE-26)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), wizardPayload(wizardPeriod([DeadlineType::Submit])))
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0);
});

it('rejects a period without Batas Submit and saves nothing (BR-FE-07)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), wizardPayload(wizardPeriod([DeadlineType::Evaluation])))
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0);
});

it('binds no Batas Evaluasi and creates no evaluation forms when the phase needs no review', function () {
    $payload = wizardPayload(wizardPeriod([DeadlineType::Submit]));
    $payload['phase']['needs_review'] = false;

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(FormPhaseDetail::query()->sole()->evaluation_date_id)->toBeNull()
        ->and(ReviewEvaluationForm::query()->count())->toBe(0);
});

it('audits the wizard build (BR-RPT-09)', function () {
    $period = wizardPeriod();

    $this->actingAs($this->admin)->post(route('admin.form-builder.store'), wizardPayload($period));

    $form = Form::query()->where('title', 'Form Wizard')->firstOrFail();
    $entry = Activity::query()
        ->where('log_name', AuditTrail::LOG_NAME)
        ->where('subject_type', $form->getMorphClass())
        ->where('subject_id', $form->id)
        ->sole();

    expect($entry->description)->toBe('Form dibangun lewat wizard')
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['new']['submission_period_id'])->toBe($period->id)
        ->and($entry->properties['new']['phase_created'])->toBeTrue();
});

it('audits that an existing phase was reused, not created (BR-RPT-09)', function () {
    $phase = FormPhase::create(['title' => 'Tahap Lama', 'is_active' => true]);

    $payload = wizardPayload(wizardPeriod());
    $payload['phase'] = [...$payload['phase'], 'use_existing' => true, 'existing_phase_id' => $phase->id];

    $this->actingAs($this->admin)->post(route('admin.form-builder.store'), $payload);

    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->sole();

    expect($entry->properties['new']['phase_created'])->toBeFalse();
});

it('tells the operator which deadline the details received (BR-FE-17)', function () {
    $period = wizardPeriod();
    $submit = wizardDate($period, DeadlineType::Submit);

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), wizardPayload($period))
        ->assertSessionHas('info', fn (string $info): bool => str_contains($info, 'Batas Submit')
            && str_contains($info, $submit->datetime->format('Y-m-d H:i'))
            && str_contains($info, 'Edit Tahap'));
});

it('rejects a force-closed period and saves nothing (BR-SCH-07)', function () {
    $payload = wizardPayload(wizardPeriod(attributes: ['is_force_closed' => true]));

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0);
});

it('rejects a period whose dates are all in the past (BR-SCH-07)', function () {
    $payload = wizardPayload(wizardPeriod(when: '-1 month'));

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0);
});

it('requires an existing period: the wizard no longer creates one (K8g)', function () {
    $payload = wizardPayload(wizardPeriod());
    $payload['submission_period'] = ['existing_period_id' => null];

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0);
});

it('offers only usable periods to the wizard (BR-SCH-07)', function () {
    $usable = wizardPeriod();
    wizardPeriod(attributes: ['is_force_closed' => true]);

    $this->actingAs($this->admin)
        ->get(route('admin.form-builder.create'))
        ->assertInertia(fn ($page) => $page
            ->has('submissionPeriods', 1)
            ->where('submissionPeriods.0.id', $usable->id)
            // K8g — label diturunkan dari jenis dan siklus (BR-FE-25); katalog label tak ada lagi.
            ->where('submissionPeriods.0.submission_dates.0.label', 'Batas Submit')
            ->where('submissionPeriods.0.submission_dates.1.label', 'Batas Evaluasi')
            ->missing('submissionDateLabels'));
});

it('rejects a trl_selector without a scheme_selector through the wizard (BR-SCH-05)', function () {
    $trl = FieldType::create(['name' => 'trl_selector']);
    $payload = wizardPayload(wizardPeriod());
    $payload['form']['fields'][] = [
        'field_type_id' => $trl->id, 'label' => 'TRL', 'is_required' => true, 'options' => [],
    ];

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('fields');

    expect(Form::query()->count())->toBe(0);
});

it('links an existing period to a new phase exactly once', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), wizardPayload(wizardPeriod()))
        ->assertSessionHasNoErrors();

    $phase = FormPhase::query()->where('title', 'Tahap Wizard')->firstOrFail();
    expect(SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->count())->toBe(1)
        ->and(SubmissionPeriodPhase::query()->count())->toBe(1);
});

it('does not link a period twice to an existing phase already using it', function () {
    $period = wizardPeriod();
    $phase = FormPhase::create(['title' => 'Tahap Lama', 'is_active' => true]);
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);

    $payload = wizardPayload($period);
    $payload['phase'] = [...$payload['phase'], 'use_existing' => true, 'existing_phase_id' => $phase->id];

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->count())->toBe(1)
        ->and($phase->formPhaseDetails()->sole()->submission_date_id)->toBe(wizardDate($period, DeadlineType::Submit)->id);
});

it('rejects an evaluation field without a field type as a validation error', function () {
    $payload = wizardPayload(wizardPeriod());
    unset($payload['evaluation_forms'][0]['fields'][0]['field_type_id']);

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertStatus(302)
        ->assertSessionHasErrors('evaluation_forms.0.fields.0.field_type_id');

    expect(Form::query()->count())->toBe(0);
});

it('grants wizard access on a whole faculty, like the access control screen (ADR 0013)', function () {
    $faculty = Organization::query()->where('name', 'Fakultas Wizard')->firstOrFail();
    $payload = wizardPayload(wizardPeriod());
    $payload['access_controls'] = [['permission' => 'submissions.create', 'study_program_id' => $faculty->id]];

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasNoErrors();

    $form = Form::query()->where('title', 'Form Wizard')->firstOrFail();
    expect($form->formAccessControls()->sole()->organization_id)->toBe($faculty->id);
});

it('rejects wizard access on an inactive organization (ADR 0013)', function () {
    $this->studyProgram->update(['is_active' => false]);
    $payload = wizardPayload(wizardPeriod());

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('access_controls.0.study_program_id');

    expect(Form::query()->where('title', 'Form Wizard')->exists())->toBeFalse();
});
