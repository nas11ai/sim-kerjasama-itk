<?php

// ADMIN-08-09 — form evaluasi reviewer di layar sunting tahap: satu bagian per detail yang
// direview (ubiquitous language), disimpan per kartu lewat route ber-detail (W95, W100).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
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
    $detail = FormPhaseDetail::factory()->create(['needs_review' => $needsReview]);
    $detail->formAccessControl?->update(['form_id' => $form->id]);
    if ($phase !== null) {
        $detail->update(['form_phase_id' => $phase->id]);
    }

    return $detail->fresh() ?? $detail;
}

it('kontrak bagian form evaluasi', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = ReviewEvaluationForm::create([
        'title' => 'Lembar Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);
    $field = ReviewFormField::create([
        'review_evaluation_form_id' => $evaluation->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'number'])->id,
        'label' => 'Metodologi', 'is_required' => true, 'order' => 1,
        'validation_rules' => ['min_value' => 1, 'max_value' => 7],
    ]);
    ReviewFormFieldOption::create(['review_form_field_id' => $field->id, 'label' => 'Layak', 'value' => 'layak', 'order' => 1]);

    $this->actingAs(phaseEvaluationActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $detail->formPhase]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->has('fieldTypeOptions.0', fn (AssertableInertia $option) => $option
                ->hasAll(['value', 'label', 'hasOptions', 'limitKind']))
            ->has('evaluation.0', fn (AssertableInertia $section) => $section
                ->where('detailId', $detail->id)
                ->where('detailLabel', "{$detail->phaseType?->name} — Form Proposal")
                ->has('forms.0', fn (AssertableInertia $form) => $form
                    ->where('id', $evaluation->id)
                    ->hasAll(['title', 'description', 'isRequired', 'isActive', 'assignmentCount'])
                    ->has('fields.0', fn (AssertableInertia $row) => $row
                        ->where('limits', ['minLength' => null, 'maxLength' => null, 'minValue' => 1, 'maxValue' => 7])
                        ->where('options.0.value', 'layak')
                        ->hasAll(['id', 'label', 'description', 'fieldTypeId', 'isRequired', 'answerCount'])))));
});

it('hanya detail yang direview punya bagian form evaluasi', function () {
    $reviewed = phaseEvaluationDetail($this->form, true);
    phaseEvaluationDetail($this->form, false, $reviewed->formPhase);

    $this->actingAs(phaseEvaluationActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $reviewed->formPhase]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('phase.details', 2)
            ->has('evaluation', 1)
            ->where('evaluation.0.detailId', $reviewed->id)
            ->where('evaluation.0.forms', []));

    // W95 — mode buat belum punya detail tersimpan, jadi belum ada bagian evaluasi.
    $this->get(route('admin.forms.phases.create', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('evaluation', [])
            ->where('fieldTypeOptions', []));
});

it('wewenang store/update: operator 403', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = ReviewEvaluationForm::create([
        'title' => 'Lembar Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);

    $this->actingAs(phaseEvaluationActor('operator'))
        ->post(route('admin.phase-details.evaluation-forms.store', $detail), ['title' => 'Dibajak'])
        ->assertForbidden();
    $this->put(route('admin.evaluation-forms.update', $evaluation), ['title' => 'Dibajak'])
        ->assertForbidden();

    expect(ReviewEvaluationForm::query()->count())->toBe(1)
        ->and($evaluation->fresh()?->title)->toBe('Lembar Substansi');
});

it('membuat form evaluasi untuk detail dari route-nya', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $other = phaseEvaluationDetail($this->form, true);

    $this->actingAs(phaseEvaluationActor())
        ->from(route('admin.forms.phases.edit', [$this->form, $detail->formPhase]))
        ->post(route('admin.phase-details.evaluation-forms.store', $detail), [
            'title' => 'Lembar Kelayakan',
            'form_phase_detail_id' => $other->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.phases.edit', [$this->form, $detail->formPhase]))
        ->assertSessionHas('success', 'Form evaluasi dibuat.');

    expect(ReviewEvaluationForm::query()->where('title', 'Lembar Kelayakan')->sole()->form_phase_detail_id)
        ->toBe($detail->id);
});
