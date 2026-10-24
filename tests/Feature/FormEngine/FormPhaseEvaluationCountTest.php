<?php

// tests/Feature/FormEngine/FormPhaseEvaluationCountTest.php
//
// Jumlah form evaluasi per tahap di layar Admin: dijumlah dari seluruh detail, hanya
// form aktif, dan yang wajib dihitung terpisah.

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $first = FormPhaseDetail::factory()->create();
    $second = FormPhaseDetail::factory()->create(['form_phase_id' => $first->form_phase_id, 'order' => 2]);
    $this->phase = FormPhase::query()->findOrFail($first->form_phase_id);

    $form = fn (FormPhaseDetail $detail, bool $required, bool $active) => ReviewEvaluationForm::create([
        'title' => 'Form '.fake()->unique()->numerify('###'),
        'form_phase_detail_id' => $detail->id,
        'is_required' => $required,
        'is_active' => $active,
        'order' => 1,
    ]);

    $form($first, required: true, active: true);
    $form($first, required: false, active: true);
    $form($first, required: true, active: false);
    $form($second, required: true, active: true);
});

it('sums active evaluation forms across details on the phase list', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.form-phases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('formPhases.data.0.review_evaluation_forms_count', 3)
            ->where('formPhases.data.0.required_review_evaluation_forms_count', 2));
});

it('sums active evaluation forms across details on the phase page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.form-phases.show', $this->phase))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('formPhase.review_evaluation_forms_count', 3)
            ->where('formPhase.required_review_evaluation_forms_count', 2));
});
