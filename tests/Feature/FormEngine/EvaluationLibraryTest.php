<?php

// ADMIN-08-09 — pustaka Form Evaluasi (evaluation_library.feature): BR-FE-30 pemasangan ke langkah,
// BR-FE-31 draf → Terbitkan.

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\PinEvaluationVersion;
use App\Actions\Review\SaveEvaluationFormDraft;
use App\Data\Request\EvaluationDraftInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Data\View\Submission\Wizard\WizardFieldData;
use App\Models\ConfigurationDraft;
use App\Models\FieldType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Queries\Review\EvaluationFormLibrary;
use App\Queries\Review\EvaluationPublishImpact;
use App\Queries\Review\ReviewerEvaluation;
use App\States\Submission\UnderReview;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->research = FormPhase::create(['title' => 'Penelitian DIPA', 'is_active' => true]);
    $this->service = FormPhase::create(['title' => 'Pengmas DIPA', 'is_active' => true]);
    $this->researchStep = scheduleStep($this->research);
    $this->serviceStep = scheduleStep($this->service);
    $this->text = FieldType::firstOrCreate(['name' => 'text'])->id;
});

/** Form Evaluasi pustaka, terbit `$versions` kali (0 = belum pernah), belum terpasang. */
function libraryForm(string $title, int $versions = 1): ReviewEvaluationForm
{
    $form = ReviewEvaluationForm::create(['title' => $title]);
    for ($i = 0; $i < $versions; $i++) {
        publishVersion($form, [['type' => 'text', 'label' => 'Catatan']]);
    }

    return $form;
}

/** @return array<string, mixed> isian draf: field teks berlabel `$labels` */
function draftPayload(int $text, string ...$labels): array
{
    return ['fields' => array_map(fn (string $label): array => [
        'field_type_id' => $text,
        'label' => $label,
        'is_required' => false,
    ], $labels)];
}

function draftInput(int $text, string ...$labels): EvaluationDraftInput
{
    return new EvaluationDraftInput(array_map(
        fn (string $label): ReviewFormFieldInput => new ReviewFormFieldInput(null, $text, $label, null, false, null, null),
        $labels,
    ));
}

/** Kursi reviewer yang ditugaskan `$form` pada pengajuan di `$step`. */
function librarySeat(FormPhaseDetail $step, ReviewEvaluationForm $form, ?string $period = null): ReviewerFormAssignment
{
    $submission = scheduledSubmission($step, schedulePeriod($step->formPhase, $period ?? fake()->unique()->numerify('Periode ####')), [
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);

    return AssignEvaluationForm::run(reviewerOn($submission, 'pending'), $form->id);
}

it('[FE-EVLIB-01] refuses to attach an evaluation form that was never published', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.evaluation-forms.store'), ['title' => 'Lembar Proposal'])
        ->assertSessionHasNoErrors();
    $form = ReviewEvaluationForm::query()->where('title', 'Lembar Proposal')->sole();

    $this->post(route('admin.phase-details.evaluation-forms.store', $this->researchStep), [
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
    ])->assertSessionHasErrors([
        'review_evaluation_form_id' => 'Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang.',
    ]);

    expect(StepEvaluationForm::query()->where('form_phase_detail_id', $this->researchStep->id)->exists())->toBeFalse();
});

it('[FE-EVLIB-02] attaches one evaluation form to steps of two phases, once per step', function () {
    $form = libraryForm('Lembar Proposal');

    $this->actingAs($this->admin)
        ->from(route('admin.phases.edit', $this->research))
        ->post(route('admin.phase-details.evaluation-forms.store', $this->researchStep), [
            'review_evaluation_form_id' => $form->id,
            'is_required' => true,
        ])
        ->assertRedirect(route('admin.phases.edit', $this->research))
        ->assertSessionHas('success', 'Form evaluasi dipasang.');
    $this->post(route('admin.phase-details.evaluation-forms.store', $this->serviceStep), [
        'review_evaluation_form_id' => $form->id,
        'is_required' => false,
    ])->assertSessionHasNoErrors();

    // BR-FE-32 — tiap pemasangan menerbitkan versi struktur berikutnya tahapnya.
    $research = $this->research->currentDetails()->sole();
    $service = $this->service->currentDetails()->sole();
    expect(StepEvaluationForm::query()->where('review_evaluation_form_id', $form->id)->count())->toBe(2)
        ->and($research->stepEvaluationForms()->sole()->is_required)->toBeTrue()
        ->and($service->stepEvaluationForms()->sole()->is_required)->toBeFalse();

    $this->post(route('admin.phase-details.evaluation-forms.store', $research), [
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
    ])->assertSessionHasErrors(['review_evaluation_form_id' => 'Form evaluasi ini sudah terpasang di langkah ini.']);
});

it('[FE-EVLIB-03] keeps reviewers on the published version while a draft is pending', function () {
    $form = evaluationFormOn($this->researchStep, [['type' => 'text', 'label' => 'Catatan']], title: 'Lembar Proposal');
    $seat = librarySeat($this->researchStep, $form);

    $this->actingAs($this->admin)
        ->put(route('admin.evaluation-forms.draft.update', $form), draftPayload($this->text, 'Catatan', 'Kebaruan'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Draf disimpan.');

    $seen = ReviewerEvaluation::run($seat->submissionReviewer->refresh(), null)['forms'][0]->fields;
    expect(array_map(fn (WizardFieldData $field): string => $field->label, $seen))->toBe(['Catatan']);

    $this->get(route('admin.evaluation-forms.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/EvaluationFormIndexPage')
            ->where('forms.0.title', 'Lembar Proposal')
            ->where('forms.0.hasDraft', true)
            ->where('forms.0.latestVersionLabel', 'Versi 1'));
});

it('[FE-EVLIB-04] publishes the draft as the next version and deletes the draft', function () {
    $form = libraryForm('Lembar Proposal');
    SaveEvaluationFormDraft::run($form, draftInput($this->text, 'Catatan', 'Kebaruan'), $this->admin);

    $this->actingAs($this->admin)
        ->post(route('admin.evaluation-forms.publish', $form))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Versi 2 diterbitkan.');

    $versions = $form->versions()->get();
    expect($versions->pluck('number')->all())->toBe([1, 2])
        ->and($versions[0]->reviewFormFields()->pluck('label')->all())->toBe(['Catatan'])
        ->and($versions[1]->reviewFormFields()->pluck('label')->all())->toBe(['Catatan', 'Kebaruan'])
        ->and(ConfigurationDraft::query()->count())->toBe(0);

    $this->post(route('admin.evaluation-forms.publish', $form))
        ->assertSessionHasErrors(['draft' => 'Tidak ada draf untuk diterbitkan.']);
    expect($form->versions()->count())->toBe(2);
});

it('[FE-EVLIB-05] detaches an assigned form only from the next version and keeps the assignment', function () {
    $form = evaluationFormOn($this->researchStep, title: 'Lembar Proposal');
    $research = StepEvaluationForm::query()->where('form_phase_detail_id', $this->researchStep->id)->sole();
    $service = StepEvaluationForm::create([
        'form_phase_detail_id' => $this->serviceStep->id,
        'review_evaluation_form_id' => $form->id,
        'order' => 1,
    ]);
    $assignment = librarySeat($this->researchStep, $form);

    $this->actingAs($this->admin)
        ->delete(route('admin.step-evaluation-forms.destroy', $research))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Form evaluasi dilepas.');
    expect($research->fresh())->not->toBeNull()
        ->and($assignment->fresh())->not->toBeNull()
        ->and($this->research->currentDetails()->sole()->stepEvaluationForms()->count())->toBe(0);

    $this->put(route('admin.step-evaluation-forms.update', $service), ['is_required' => true, 'is_active' => false])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Pemasangan diperbarui.');
    expect($this->service->currentDetails()->sole()->stepEvaluationForms()->sole()->is_active)->toBeFalse()
        ->and($service->fresh()?->is_active)->toBeTrue();
});

it('lists library forms with their latest version and draft flag', function () {
    $published = evaluationFormOn($this->researchStep, title: 'Lembar Proposal');
    publishVersion($published, [['type' => 'text', 'label' => 'Kebaruan']]);
    SaveEvaluationFormDraft::run($published, draftInput($this->text, 'Kebaruan', 'Dampak'), $this->admin);
    librarySeat($this->researchStep, $published);
    libraryForm('Lembar Baru', versions: 0);

    $this->actingAs($this->admin)
        ->get(route('admin.evaluation-forms.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/EvaluationFormIndexPage')
            ->has('forms', 2)
            ->where('forms.0', [
                'id' => ReviewEvaluationForm::query()->where('title', 'Lembar Baru')->value('id'),
                'title' => 'Lembar Baru',
                'latestVersionLabel' => null,
                'hasDraft' => false,
                'stepCount' => 0,
                'assignmentCount' => 0,
            ])
            ->where('forms.1', [
                'id' => $published->id,
                'title' => 'Lembar Proposal',
                'latestVersionLabel' => 'Versi 2',
                'hasDraft' => true,
                'stepCount' => 1,
                'assignmentCount' => 1,
            ]));
});

it('reads the library without a query per card', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        EvaluationFormLibrary::run();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    libraryForm('Satu');
    $few = $count();
    libraryForm('Dua', versions: 2);
    libraryForm('Tiga', versions: 0);

    expect($count())->toBe($few);
});

it('renders the editor with the draft fields, versions, steps that use the form, and publish impact', function () {
    $form = evaluationFormOn($this->researchStep, [['type' => 'text', 'label' => 'Catatan']], title: 'Lembar Proposal');
    SaveEvaluationFormDraft::run($form, draftInput($this->text, 'Catatan', 'Kebaruan'), $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.evaluation-forms.edit', $form))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/EvaluationFormEditPage')
            ->has('fieldTypeOptions.0')
            ->where('form.id', $form->id)
            ->where('form.title', 'Lembar Proposal')
            ->where('form.hasDraft', true)
            ->where('form.latestVersionLabel', 'Versi 1')
            ->where('form.fields.1.label', 'Kebaruan')
            ->has('form.fields.1', fn (AssertableInertia $field) => $field
                ->hasAll(['label', 'description', 'fieldTypeId', 'isRequired', 'limits', 'options']))
            ->has('form.versions', 1)
            ->where('form.versions.0.label', 'Versi 1')
            ->where('form.usedBy', [[
                'phaseId' => $this->research->id,
                'phaseTitle' => 'Penelitian DIPA',
                'stepLabel' => $this->researchStep->deadline_type->stepLabel($this->researchStep->deadline_cycle),
            ]])
            ->where('form.impact', ['nextLabel' => 'Versi 2', 'pinnedCohorts' => 0]));
});

it('counts cohorts that keep an older version in the publish impact', function () {
    $form = evaluationFormOn($this->researchStep, title: 'Lembar Proposal');
    expect(EvaluationPublishImpact::run($form))->toBe(['nextLabel' => 'Versi 2', 'pinnedCohorts' => 0]);

    foreach (['2025', '2026'] as $period) {
        PinEvaluationVersion::run(librarySeat($this->researchStep, $form, $period)->submissionReviewer->formSubmission, $form);
    }

    expect(EvaluationPublishImpact::run($form))->toBe(['nextLabel' => 'Versi 2', 'pinnedCohorts' => 2]);
});

it('creates a library form and opens its editor', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.evaluation-forms.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/EvaluationFormEditPage')
            ->where('form', null));

    $response = $this->post(route('admin.evaluation-forms.store'), ['title' => 'Lembar Proposal', 'description' => '']);
    $form = ReviewEvaluationForm::query()->where('title', 'Lembar Proposal')->sole();

    $response->assertRedirect(route('admin.evaluation-forms.edit', $form))
        ->assertSessionHas('success', 'Form evaluasi dibuat. Susun field-nya lalu terbitkan.');
    expect($form->description)->toBeNull()
        ->and($form->versions()->exists())->toBeFalse();
});

it('renames a library form without publishing a version', function () {
    $form = libraryForm('Lembar Proposal');

    $this->actingAs($this->admin)
        ->put(route('admin.evaluation-forms.update', $form), ['title' => 'Lembar Proposal 2026', 'description' => 'Untuk skema DIPA'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Form evaluasi diperbarui.');

    expect($form->fresh()?->title)->toBe('Lembar Proposal 2026')
        ->and($form->versions()->count())->toBe(1);
});

it('discards a draft without leaving a version', function () {
    $form = libraryForm('Lembar Proposal');
    SaveEvaluationFormDraft::run($form, draftInput($this->text, 'Kebaruan'), $this->admin);

    $this->actingAs($this->admin)
        ->delete(route('admin.evaluation-forms.draft.destroy', $form))
        ->assertSessionHas('success', 'Draf dibuang.');

    expect(ConfigurationDraft::query()->count())->toBe(0)
        ->and($form->versions()->count())->toBe(1);
});

it('rejects a draft field without a field type', function () {
    $form = libraryForm('Lembar Proposal');

    $this->actingAs($this->admin)
        ->put(route('admin.evaluation-forms.draft.update', $form), ['fields' => [['label' => 'Skor']]])
        ->assertSessionHasErrors('fields.0.field_type_id');

    expect(ConfigurationDraft::query()->count())->toBe(0);
});

it('refuses to delete a form still attached to a step', function () {
    $form = evaluationFormOn($this->researchStep, title: 'Lembar Proposal');

    $this->actingAs($this->admin)
        ->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertSessionHasErrors('id');

    expect($form->fresh())->not->toBeNull();
});

it('deletes an unattached form together with its draft', function () {
    $form = libraryForm('Lembar Proposal');
    SaveEvaluationFormDraft::run($form, draftInput($this->text, 'Kebaruan'), $this->admin);

    $this->actingAs($this->admin)
        ->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertRedirect(route('admin.evaluation-forms.index'))
        ->assertSessionHas('success', 'Form evaluasi dihapus.');

    expect($form->fresh())->toBeNull()
        ->and(ConfigurationDraft::query()->count())->toBe(0);
});

it('hides the library from users without schemes.manage', function () {
    $form = libraryForm('Lembar Proposal');
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)->get(route('admin.evaluation-forms.index'))->assertForbidden();
    $this->get(route('admin.evaluation-forms.edit', $form))->assertForbidden();
    $this->put(route('admin.evaluation-forms.draft.update', $form), draftPayload($this->text, 'Dibajak'))->assertForbidden();
    $this->post(route('admin.phase-details.evaluation-forms.store', $this->researchStep), [
        'review_evaluation_form_id' => $form->id,
    ])->assertForbidden();

    expect(ConfigurationDraft::query()->count())->toBe(0)
        ->and(StepEvaluationForm::query()->count())->toBe(0);
});
