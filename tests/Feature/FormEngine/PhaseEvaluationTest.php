<?php

// ADMIN-08-09 — Form Evaluasi reviewer di editor tahap: dipasang per langkah sebagai bagian draf
// struktur (BR-FE-30, BR-FE-33), berlaku sejak versi yang diterbitkan.

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create(['title' => 'Form Proposal']);
});

function phaseEvaluationActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Detail beraturan akses milik $form, di tahap $phase (atau tahap baru). */
function phaseEvaluationDetail(Form $form, bool $needsReview, ?FormPhase $phase = null): FormPhaseDetail
{
    return formStepIn($form, $phase, ['needs_review' => $needsReview]);
}

/** Kiriman draf satu langkah `$detail` dengan pemasangan `$forms`. */
function phaseEvaluationDraft(FormPhaseDetail $detail, array $forms, ?bool $needsReview = null): array
{
    return ['phase_details' => [[
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'needs_review' => $needsReview ?? $detail->needs_review,
        'deadline_type' => $detail->deadline_type->value,
        'deadline_cycle' => $detail->deadline_cycle,
        'evaluation_forms' => array_map(fn (ReviewEvaluationForm $form): array => [
            'review_evaluation_form_id' => $form->id,
            'is_required' => true,
            'is_active' => true,
        ], $forms),
    ]]];
}

function publishedEvaluation(string $title): ReviewEvaluationForm
{
    $form = ReviewEvaluationForm::create(['title' => $title]);
    publishVersion($form, [['type' => 'text', 'label' => 'Catatan']]);

    return $form;
}

it('kontrak pemasangan form evaluasi di langkah', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = evaluationFormOn($detail, title: 'Lembar Substansi');
    publishVersion($evaluation, [['type' => 'text', 'label' => 'Metodologi']]);
    $other = publishedEvaluation('Lembar Anggaran');
    ReviewEvaluationForm::create(['title' => 'Lembar Belum Terbit']);

    $this->actingAs(phaseEvaluationActor())
        ->get(route('admin.phases.edit', $detail->formPhase))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->missing('fieldTypeOptions')
            ->missing('evaluation')
            // BR-FE-30 — hanya Form Evaluasi terbit yang bisa dipasang, urut judul.
            ->where('evaluationOptions', [
                ['value' => (string) $other->id, 'label' => 'Lembar Anggaran', 'versionLabel' => 'Versi 1'],
                ['value' => (string) $evaluation->id, 'label' => 'Lembar Substansi', 'versionLabel' => 'Versi 2'],
            ])
            ->where('phase.steps.0.evaluationForms', [[
                'formId' => $evaluation->id,
                'title' => 'Lembar Substansi',
                'versionLabel' => 'Versi 2',
                'isRequired' => true,
                'isActive' => true,
            ]]));

    // BR-FE-33 — mode buat juga menyusun draf, jadi pilihan pemasangan ikut tersedia.
    $this->get(route('admin.phases.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('evaluationOptions', 2));
});

it('wewenang pasang: peneliti 403', function () {
    $detail = phaseEvaluationDetail($this->form, true);

    $this->actingAs(phaseEvaluationActor('researcher'))
        ->put(route('admin.phases.draft.update', $detail->formPhase), phaseEvaluationDraft($detail, [publishedEvaluation('Lembar Substansi')]))
        ->assertForbidden();

    expect($detail->formPhase?->draft()->exists())->toBeFalse();
});

it('memasang form evaluasi lewat draf, berlaku sejak versi terbitnya', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = publishedEvaluation('Lembar Kelayakan');
    $edit = route('admin.phases.edit', $detail->formPhase);

    $this->actingAs(phaseEvaluationActor())
        ->from($edit)
        ->put(route('admin.phases.draft.update', $detail->formPhase), phaseEvaluationDraft($detail, [$evaluation]))
        ->assertSessionHasNoErrors()
        ->assertRedirect($edit);

    // Draf tidak memasang apa pun sebelum terbit.
    expect(StepEvaluationForm::query()->count())->toBe(0);

    $this->post(route('admin.phases.publish', $detail->formPhase))->assertSessionHasNoErrors();

    expect($evaluation->stepEvaluationForms()->sole()->form_phase_detail_id)
        ->toBe($detail->formPhase->currentDetails()->sole()->id)
        ->and($detail->stepEvaluationForms()->count())->toBe(0);
});

it('menolak memasang di langkah yang tidak direview saat draf disimpan', function () {
    $detail = phaseEvaluationDetail($this->form, false);

    $this->actingAs(phaseEvaluationActor())
        ->put(route('admin.phases.draft.update', $detail->formPhase), phaseEvaluationDraft($detail, [publishedEvaluation('Lembar Kelayakan')]))
        ->assertSessionHasErrors(['phase_details.0.needs_review' => 'Detail ini punya 1 form evaluasi aktif, jadi tetap harus direview.']);

    expect($detail->formPhase?->draft()->exists())->toBeFalse();
});

it('menolak form evaluasi yang dipasang dua kali di satu langkah', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = publishedEvaluation('Lembar Kelayakan');

    $this->actingAs(phaseEvaluationActor())
        ->put(route('admin.phases.draft.update', $detail->formPhase), phaseEvaluationDraft($detail, [$evaluation, $evaluation]))
        ->assertSessionHasErrors(['review_evaluation_form_id' => 'Form evaluasi ini sudah terpasang di langkah ini.']);
});
