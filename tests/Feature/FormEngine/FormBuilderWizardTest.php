<?php

// tests/Feature/FormEngine/FormBuilderWizardTest.php
//
// Wizard enam langkah (`admin.form-builder.*`). BR-FE-07 — setiap detail tahap wajib punya
// tenggat; wizard memakai tanggal terakhir periode yang dipilih atau dibuat.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
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
    $this->labelA = SubmissionDateLabel::create(['name' => 'Batas Proposal']);
    $this->labelB = SubmissionDateLabel::create(['name' => 'Batas Laporan']);
});

/**
 * @param  array<string, mixed>  $period
 * @return array<string, mixed>
 */
function wizardPayload(array $period): array
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
        'submission_period' => $period,
    ];
}

/**
 * @param  list<array{label_id: int, date: string}>  $dates
 * @return array<string, mixed>
 */
function newWizardPeriod(array $dates): array
{
    return [
        'use_existing' => false, 'existing_period_id' => null,
        'new_period_name' => 'Periode Wizard', 'dates' => $dates,
    ];
}

it('saves the whole form with the latest period date as the detail deadline (BR-FE-07)', function () {
    $payload = wizardPayload(newWizardPeriod([
        ['label_id' => $this->labelB->id, 'date' => '2030-03-01 10:00'],
        ['label_id' => $this->labelA->id, 'date' => '2030-01-01 10:00'],
    ]));

    $response = $this->actingAs($this->admin)->post(route('admin.form-builder.store'), $payload);

    $form = Form::query()->where('title', 'Form Wizard')->firstOrFail();
    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.forms.show', $form));

    $period = SubmissionPeriod::query()->where('name', 'Periode Wizard')->firstOrFail();
    $latest = $period->submissionDates()->where('submission_date_label_id', $this->labelB->id)->firstOrFail();
    $phase = FormPhase::query()->where('title', 'Tahap Wizard')->firstOrFail();
    $detail = FormPhaseDetail::query()->where('form_phase_id', $phase->id)->sole();

    expect($form->formFields()->sole()->formFieldOptions()->count())->toBe(1)
        ->and($form->formAccessControls()->sole()->organization_id)->toBe($this->studyProgram->id)
        ->and($detail->submission_date_id)->toBe($latest->id)
        ->and($period->submissionDates()->count())->toBe(2)
        ->and(SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->count())->toBe(1);

    // BR-FE-17 — tenggatnya milik periode yang memakai tahap itu.
    expect(SubmissionDate::query()->selectableFor($phase)->pluck('id')->all())->toContain($latest->id);

    $evaluation = ReviewEvaluationForm::query()->where('form_phase_detail_id', $detail->id)->sole();
    expect($evaluation->reviewFormFields()->sole()->reviewFormFieldOptions()->count())->toBe(1);
});

it('audits the wizard build (BR-RPT-09)', function () {
    $payload = wizardPayload(newWizardPeriod([['label_id' => $this->labelA->id, 'date' => '2030-01-01']]));

    $this->actingAs($this->admin)->post(route('admin.form-builder.store'), $payload);

    $form = Form::query()->where('title', 'Form Wizard')->firstOrFail();
    $entry = Activity::query()
        ->where('log_name', AuditTrail::LOG_NAME)
        ->where('subject_type', $form->getMorphClass())
        ->where('subject_id', $form->id)
        ->sole();

    $period = SubmissionPeriod::query()->where('name', 'Periode Wizard')->firstOrFail();

    expect($entry->description)->toBe('Form dibangun lewat wizard')
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['new']['period_created'])->toBeTrue()
        ->and($entry->properties['new']['phase_created'])->toBeTrue()
        ->and($entry->properties['new']['created_date_ids'])
        ->toBe($period->submissionDates()->pluck('id')->all());
});

it('audits that an existing period and phase were reused, not created (BR-RPT-09)', function () {
    $period = SubmissionPeriod::factory()->create();
    SubmissionDate::create([
        'submission_period_id' => $period->id,
        'submission_date_label_id' => $this->labelA->id,
        'datetime' => now()->addMonth(),
    ]);
    $phase = FormPhase::create(['title' => 'Tahap Lama', 'is_active' => true]);

    $payload = wizardPayload([
        'use_existing' => true, 'existing_period_id' => $period->id, 'new_period_name' => '', 'dates' => [],
    ]);
    $payload['phase'] = [...$payload['phase'], 'use_existing' => true, 'existing_phase_id' => $phase->id];

    $this->actingAs($this->admin)->post(route('admin.form-builder.store'), $payload);

    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->sole();

    expect($entry->properties['new']['period_created'])->toBeFalse()
        ->and($entry->properties['new']['phase_created'])->toBeFalse()
        ->and($entry->properties['new']['created_date_ids'])->toBe([]);
});

it('tells the operator which deadline the details received (BR-FE-17)', function () {
    $payload = wizardPayload(newWizardPeriod([
        ['label_id' => $this->labelB->id, 'date' => '2030-03-01 10:00'],
        ['label_id' => $this->labelA->id, 'date' => '2030-01-01 10:00'],
    ]));

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHas('info', fn (string $info): bool => str_contains($info, 'Batas Laporan')
            && str_contains($info, '2030-03-01 10:00')
            && str_contains($info, 'Edit Tahap'));
});

it('rejects a force-closed existing period and saves nothing (BR-SCH-07)', function () {
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => true]);
    SubmissionDate::create([
        'submission_period_id' => $period->id,
        'submission_date_label_id' => $this->labelA->id,
        'datetime' => now()->addMonth(),
    ]);
    $payload = wizardPayload([
        'use_existing' => true, 'existing_period_id' => $period->id, 'new_period_name' => '', 'dates' => [],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0);
});

it('rejects an existing period whose dates are all in the past (BR-SCH-07)', function () {
    $period = SubmissionPeriod::factory()->create();
    SubmissionDate::create([
        'submission_period_id' => $period->id,
        'submission_date_label_id' => $this->labelA->id,
        'datetime' => now()->subDay(),
    ]);
    $payload = wizardPayload([
        'use_existing' => true, 'existing_period_id' => $period->id, 'new_period_name' => '', 'dates' => [],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('submission_period.existing_period_id');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0);
});

it('offers only usable periods to the wizard (BR-SCH-07)', function () {
    $usable = SubmissionPeriod::factory()->create();
    $closed = SubmissionPeriod::factory()->create(['is_force_closed' => true]);
    foreach ([$usable, $closed] as $period) {
        SubmissionDate::create([
            'submission_period_id' => $period->id,
            'submission_date_label_id' => $this->labelA->id,
            'datetime' => now()->addMonth(),
        ]);
    }

    $this->actingAs($this->admin)
        ->get(route('admin.form-builder.create'))
        ->assertInertia(fn ($page) => $page
            ->has('submissionPeriods', 1)
            ->where('submissionPeriods.0.id', $usable->id));
});

it('rejects a trl_selector without a scheme_selector through the wizard (BR-SCH-05)', function () {
    $trl = FieldType::create(['name' => 'trl_selector']);
    $payload = wizardPayload(newWizardPeriod([['label_id' => $this->labelA->id, 'date' => '2030-01-01']]));
    $payload['form']['fields'][] = [
        'field_type_id' => $trl->id, 'label' => 'TRL', 'is_required' => true, 'options' => [],
    ];

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('fields');

    expect(Form::query()->count())->toBe(0)
        ->and(SubmissionPeriod::query()->count())->toBe(0);
});

it('links an existing period to a new phase exactly once', function () {
    $period = SubmissionPeriod::factory()->create();
    SubmissionDate::create([
        'submission_period_id' => $period->id,
        'submission_date_label_id' => $this->labelA->id,
        'datetime' => now()->addMonth(),
    ]);
    $payload = wizardPayload([
        'use_existing' => true, 'existing_period_id' => $period->id, 'new_period_name' => '', 'dates' => [],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasNoErrors();

    $phase = FormPhase::query()->where('title', 'Tahap Wizard')->firstOrFail();
    expect(SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->count())->toBe(1)
        ->and(SubmissionPeriodPhase::query()->count())->toBe(1);
});

it('creates no evaluation forms when the phase needs no review', function () {
    $payload = wizardPayload(newWizardPeriod([['label_id' => $this->labelA->id, 'date' => '2030-01-01']]));
    $payload['phase']['needs_review'] = false;

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(FormPhaseDetail::query()->count())->toBe(1)
        ->and(ReviewEvaluationForm::query()->count())->toBe(0);
});

it('does not link an existing period twice to an existing phase already using it', function () {
    $period = SubmissionPeriod::factory()->create();
    $date = SubmissionDate::create([
        'submission_period_id' => $period->id,
        'submission_date_label_id' => $this->labelA->id,
        'datetime' => now()->addMonth(),
    ]);
    $phase = FormPhase::create(['title' => 'Tahap Lama', 'is_active' => true]);
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);

    $payload = wizardPayload([
        'use_existing' => true, 'existing_period_id' => $period->id, 'new_period_name' => '', 'dates' => [],
    ]);
    $payload['phase'] = [...$payload['phase'], 'use_existing' => true, 'existing_phase_id' => $phase->id];

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->count())->toBe(1)
        ->and($phase->formPhaseDetails()->sole()->submission_date_id)->toBe($date->id);
});

it('rejects a new period without dates and saves nothing (BR-FE-07)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), wizardPayload(newWizardPeriod([])))
        ->assertSessionHasErrors('submission_period');

    expect(Form::query()->count())->toBe(0)
        ->and(FormPhase::query()->count())->toBe(0)
        ->and(SubmissionPeriod::query()->count())->toBe(0);
});

it('rejects an evaluation field without a field type as a validation error', function () {
    $payload = wizardPayload(newWizardPeriod([['label_id' => $this->labelA->id, 'date' => '2030-01-01']]));
    unset($payload['evaluation_forms'][0]['fields'][0]['field_type_id']);

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertStatus(302)
        ->assertSessionHasErrors('evaluation_forms.0.fields.0.field_type_id');

    expect(Form::query()->count())->toBe(0);
});

it('rejects two dates of a new period with the same label', function () {
    $payload = wizardPayload(newWizardPeriod([
        ['label_id' => $this->labelA->id, 'date' => '2030-01-01'],
        ['label_id' => $this->labelA->id, 'date' => '2030-02-01'],
    ]));

    $this->actingAs($this->admin)
        ->post(route('admin.form-builder.store'), $payload)
        ->assertSessionHasErrors('submission_period.dates.1.label_id');

    expect(SubmissionPeriod::query()->count())->toBe(0);
});

it('offers the date labels stored in the database to the wizard', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.form-builder.create'))
        ->assertInertia(fn ($page) => $page
            ->component('FormBuilder/CreatePage')
            ->has('submissionDateLabels', 2)
            ->where('submissionDateLabels.0', ['id' => $this->labelB->id, 'name' => 'Batas Laporan'])
            ->where('submissionDateLabels.1', ['id' => $this->labelA->id, 'name' => 'Batas Proposal']));
});
