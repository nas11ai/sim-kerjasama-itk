<?php

// tests/Feature/Review/EvaluationFormCrudTest.php
//
// Kartu form evaluasi di editor tahap lewat Action beraudit (BR-RPT-09). BR-REV-18 — simpan field
// menerbitkan versi baru (salin baris), jadi versi lama dan jawaban reviewer tidak pernah tersentuh.

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
    $this->evaluation = fn (?FormPhaseDetail $detail = null): ReviewEvaluationForm => evaluationFormOn($detail ?? FormPhaseDetail::factory()->create(['needs_review' => true]), [], title: 'Substansi');
    $this->field = fn (ReviewEvaluationForm $form, string $label): ReviewFormField => ReviewFormField::create([
        'review_evaluation_form_version_id' => $form->latestVersion->id,
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
    $field = $form->latestVersion->reviewFormFields()->firstOrFail();

    expect($form->latestVersion->label())->toBe('Versi 1')
        ->and($field->validation_rules)->toBe(['max_length' => 500])
        ->and($field->reviewFormFieldOptions()->pluck('value')->all())->toBe(['layak'])
        ->and(evaluationAudit($form, 'Form evaluasi dibuat'))->not->toBeNull()
        ->and(evaluationAudit($form, 'Form evaluasi diterbitkan')?->properties['new']['fields'])->toBe(['Rekomendasi']);
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

it('ignores a field id from another evaluation form and leaves that form intact', function () {
    $form = ($this->evaluation)();
    $foreign = ($this->field)(($this->evaluation)(), 'Milik Form Lain');

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, [
        ['id' => $foreign->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Dibajak'],
    ]))->assertSessionHasNoErrors();

    expect($foreign->fresh()->label)->toBe('Milik Form Lain')
        ->and($form->fresh()->latestVersion->reviewFormFields()->pluck('label')->all())->toBe(['Dibajak']);
});

it('ignores an option id from another field and leaves that option intact', function () {
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
    ]]))->assertSessionHasNoErrors();

    expect($foreign->fresh()->label)->toBe('Asli')
        ->and($foreign->fresh()->review_form_field_id)->not->toBe($field->id);
});

it('refuses to publish a version without fields, keeping the answers', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, []))
        ->assertSessionHasErrors(['fields' => 'Form evaluasi butuh minimal satu field.']);

    expect(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue()
        ->and($form->versions()->count())->toBe(1);
});

it('drops an option in the next version while old answers still read its label', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $field = $answer->reviewFormField;
    ReviewFormFieldOption::create(['review_form_field_id' => $field->id, 'label' => 'Layak', 'value' => 'layak', 'order' => 1]);
    $form = $field->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, [
        ['id' => $field->id, 'field_type_id' => $field->field_type_id, 'label' => 'Uji', 'options' => []],
    ]))->assertSessionHasNoErrors();

    expect($form->fresh()->latestVersion->reviewFormFields()->sole()->reviewFormFieldOptions()->count())->toBe(0)
        ->and($field->reviewFormFieldOptions()->count())->toBe(1)
        ->and($answer->fresh()->formatted_value)->toBe('Layak');
});

it('publishes revised fields as the next version, keeping the old version, audited', function () {
    $form = ($this->evaluation)();
    $kept = ($this->field)($form, 'Tetap');
    ($this->field)($form, 'Dibuang');

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), evaluationPayload($form, [
        ['id' => $kept->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Tetap Diubah'],
    ]))->assertSessionHasNoErrors();

    $versions = $form->versions()->get();
    expect($versions->pluck('number')->all())->toBe([1, 2])
        ->and($versions[0]->reviewFormFields()->pluck('label')->all())->toBe(['Tetap', 'Dibuang'])
        ->and($versions[1]->reviewFormFields()->pluck('label')->all())->toBe(['Tetap Diubah'])
        ->and($kept->fresh()->label)->toBe('Tetap')
        ->and(evaluationAudit($form, 'Form evaluasi diterbitkan')?->properties['new'])->toBe(['number' => 2, 'fields' => ['Tetap Diubah']]);
});

it('saves the phase-screen edit dialog, which sends neither the detail nor the fields', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), [
        'title' => 'Judul Baru',
        'description' => '',
        'is_required' => true,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($form->fresh()->title)->toBe('Judul Baru')
        ->and($form->versions()->count())->toBe(1)
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
    $form = evaluationFieldResponse('select', 'layak')->reviewFormField->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin)->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertSessionHasErrors(['step_evaluation_form' => 'Form evaluasi ini sudah ditugaskan ke 1 reviewer di langkah ini, jadi tidak bisa dilepas. Nonaktifkan saja.']);

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
