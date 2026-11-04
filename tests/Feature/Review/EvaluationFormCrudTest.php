<?php

// tests/Feature/Review/EvaluationFormCrudTest.php
//
// Layar form evaluasi reviewer lewat Action beraudit (BR-RPT-09): field dan opsi hanya milik
// form yang disunting, jawaban reviewer tidak ikut terhapus, form hanya untuk detail yang direview.

use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use App\Models\ReviewFormFieldResponse;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $this->fieldType = FieldType::firstOrCreate(['name' => 'select']);
    $this->evaluation = fn (?FormPhaseDetail $detail = null): ReviewEvaluationForm => ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => ($detail ?? FormPhaseDetail::factory()->create(['needs_review' => true]))->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);
    $this->field = fn (ReviewEvaluationForm $form, string $label): ReviewFormField => ReviewFormField::create([
        'review_evaluation_form_id' => $form->id,
        'field_type_id' => $this->fieldType->id,
        'label' => $label,
        'is_required' => false,
        'order' => 1,
    ]);
});

function evaluationAudit(ReviewEvaluationForm $form, string $description): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $form->getMorphClass())
        ->where('subject_id', $form->id)
        ->where('description', $description)
        ->first();
}

/** @param  list<array<string, mixed>>  $fields */
function evaluationPayload(ReviewEvaluationForm $form, array $fields): array
{
    return ['title' => 'Substansi Disunting', 'fields' => $fields];
}

it('creates an evaluation form without a description, with its fields and options, audited', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $this->actingAs($this->admin)->post(route('admin.phase-details.evaluation-forms.store', $detail), [
        'title' => 'Kelayakan',
        'fields' => [[
            'field_type_id' => $this->fieldType->id,
            'label' => 'Rekomendasi',
            'validation_rules' => ['max_length' => 500],
            'options' => [['label' => 'Layak', 'value' => 'layak']],
        ]],
    ])->assertSessionHasNoErrors();

    $form = ReviewEvaluationForm::query()->where('title', 'Kelayakan')->firstOrFail();
    $field = $form->reviewFormFields()->firstOrFail();

    expect($field->validation_rules)->toBe(['max_length' => 500])
        ->and($field->reviewFormFieldOptions()->pluck('value')->all())->toBe(['layak'])
        ->and(evaluationAudit($form, 'Form evaluasi dibuat')?->properties['new']['fields'])->toContain('Rekomendasi');
});

it('rejects an evaluation field without a field type as a validation error', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $this->actingAs($this->admin)->post(route('admin.phase-details.evaluation-forms.store', $detail), [
        'title' => 'Kelayakan',
        'fields' => [['label' => 'Skor']],
    ])->assertStatus(302)->assertSessionHasErrors('fields.0.field_type_id');

    expect(ReviewEvaluationForm::query()->count())->toBe(0);
});

it('rejects a phase detail that is not reviewed', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => false]);

    $this->actingAs($this->admin)->post(route('admin.phase-details.evaluation-forms.store', $detail), [
        'title' => 'Kelayakan',
    ])->assertSessionHasErrors('form_phase_detail_id');

    expect(ReviewEvaluationForm::query()->where('title', 'Kelayakan')->exists())->toBeFalse();
});

it('rejects a field id that belongs to another evaluation form and leaves that form intact', function () {
    $form = ($this->evaluation)();
    $foreign = ($this->field)(($this->evaluation)(), 'Milik Form Lain');

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, [
        ['id' => $foreign->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Dibajak'],
    ]))->assertSessionHasErrors('fields.0.id');

    expect($foreign->fresh()->review_evaluation_form_id)->not->toBe($form->id)
        ->and($foreign->fresh()->label)->toBe('Milik Form Lain');
});

it('rejects an option id that belongs to another field', function () {
    $form = ($this->evaluation)();
    $field = ($this->field)($form, 'Rekomendasi');
    $foreign = ReviewFormFieldOption::create([
        'review_form_field_id' => ($this->field)(($this->evaluation)(), 'Lain')->id,
        'label' => 'Asli',
        'order' => 1,
    ]);

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, [[
        'id' => $field->id,
        'field_type_id' => $this->fieldType->id,
        'label' => 'Rekomendasi',
        'options' => [['id' => $foreign->id, 'label' => 'Dibajak']],
    ]]))->assertSessionHasErrors('fields.0.options.0.id');

    expect($foreign->fresh()->label)->toBe('Asli');
});

it('refuses to drop a field that reviewers have answered, keeping the answers', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationForm;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, []))
        ->assertSessionHasErrors('fields');

    expect(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue();
});

it('refuses to change the type of an answered field', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $field = $answer->reviewFormField;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $field->reviewEvaluationForm), evaluationPayload($field->reviewEvaluationForm, [
        ['id' => $field->id, 'field_type_id' => FieldType::firstOrCreate(['name' => 'textarea'])->id, 'label' => 'Uji'],
    ]))->assertSessionHasErrors('fields.0.field_type_id');

    expect($field->fresh()->field_type_id)->toBe($field->field_type_id);
});

it('soft-deletes a dropped option: no longer offered, old answers still read its label', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $field = $answer->reviewFormField;
    $option = ReviewFormFieldOption::create(['review_form_field_id' => $field->id, 'label' => 'Layak', 'value' => 'layak', 'order' => 1]);

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $field->reviewEvaluationForm), evaluationPayload($field->reviewEvaluationForm, [
        ['id' => $field->id, 'field_type_id' => $field->field_type_id, 'label' => 'Uji', 'options' => []],
    ]))->assertSessionHasNoErrors();

    expect($field->reviewFormFieldOptions()->count())->toBe(0)
        ->and(ReviewFormFieldOption::withTrashed()->find($option->id))->not->toBeNull()
        ->and($answer->fresh()->formatted_value)->toBe('Layak');
});

it('revises fields and drops an unanswered one, audited', function () {
    $form = ($this->evaluation)();
    $kept = ($this->field)($form, 'Tetap');
    $dropped = ($this->field)($form, 'Dibuang');

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, [
        ['id' => $kept->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Tetap Diubah'],
    ]))->assertSessionHasNoErrors();

    expect(ReviewFormField::query()->whereKey($dropped->id)->exists())->toBeFalse()
        ->and($kept->fresh()->label)->toBe('Tetap Diubah')
        ->and(evaluationAudit($form, 'Form evaluasi diubah')?->properties['old']['fields'])->toContain('Dibuang');
});

it('saves the phase-screen edit dialog, which sends neither the detail nor the fields', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationForm;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), [
        'title' => 'Judul Baru',
        'description' => '',
        'is_required' => true,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($form->fresh()->title)->toBe('Judul Baru')
        ->and(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue();
});

it('deletes an unassigned evaluation form with its fields, audited', function () {
    $form = ($this->evaluation)();
    $field = ($this->field)($form, 'Rekomendasi');

    $this->actingAs($this->admin)->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertRedirect()
        ->assertSessionHas('success', 'Form evaluasi dihapus.');

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeFalse()
        ->and(ReviewFormField::query()->whereKey($field->id)->exists())->toBeFalse()
        ->and(evaluationAudit($form, 'Form evaluasi dihapus'))->not->toBeNull();
});

it('refuses to delete an assigned evaluation form and leaves no audit entry', function () {
    $form = evaluationFieldResponse('select', 'layak')->reviewFormField->reviewEvaluationForm;

    $this->actingAs($this->admin)->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertSessionHasErrors('id');

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue()
        ->and(evaluationAudit($form, 'Form evaluasi dihapus'))->toBeNull();
});

it('wewenang hapus: operator 403', function () {
    $form = ($this->evaluation)();
    Role::findOrCreate('Operator');
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $this->actingAs($operator)->delete(route('admin.evaluation-forms.destroy', $form))->assertForbidden();

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue();
});
