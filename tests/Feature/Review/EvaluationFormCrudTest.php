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
    return ['title' => 'Substansi Disunting', 'form_phase_detail_id' => $form->form_phase_detail_id, 'fields' => $fields];
}

it('creates an evaluation form without a description, with its fields and options, audited', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $this->actingAs($this->admin)->post(route('admin.review-evaluation-forms.store'), [
        'title' => 'Kelayakan',
        'form_phase_detail_id' => $detail->id,
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

it('rejects a phase detail that is not reviewed', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => false]);

    $this->actingAs($this->admin)->post(route('admin.review-evaluation-forms.store'), [
        'title' => 'Kelayakan',
        'form_phase_detail_id' => $detail->id,
    ])->assertSessionHasErrors('form_phase_detail_id');

    expect(ReviewEvaluationForm::query()->where('title', 'Kelayakan')->exists())->toBeFalse();
});

it('rejects a field id that belongs to another evaluation form and leaves that form intact', function () {
    $form = ($this->evaluation)();
    $foreign = ($this->field)(($this->evaluation)(), 'Milik Form Lain');

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $form), evaluationPayload($form, [
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

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $form), evaluationPayload($form, [[
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

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $form), evaluationPayload($form, []))
        ->assertSessionHasErrors('fields');

    expect(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue();
});

it('refuses to change the type of an answered field', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $field = $answer->reviewFormField;

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $field->reviewEvaluationForm), evaluationPayload($field->reviewEvaluationForm, [
        ['id' => $field->id, 'field_type_id' => FieldType::firstOrCreate(['name' => 'textarea'])->id, 'label' => 'Uji'],
    ]))->assertSessionHasErrors('fields.0.field_type_id');

    expect($field->fresh()->field_type_id)->toBe($field->field_type_id);
});

it('soft-deletes a dropped option: no longer offered, old answers still read its label', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $field = $answer->reviewFormField;
    $option = ReviewFormFieldOption::create(['review_form_field_id' => $field->id, 'label' => 'Layak', 'value' => 'layak', 'order' => 1]);

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $field->reviewEvaluationForm), evaluationPayload($field->reviewEvaluationForm, [
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

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $form), evaluationPayload($form, [
        ['id' => $kept->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Tetap Diubah'],
    ]))->assertSessionHasNoErrors();

    expect(ReviewFormField::query()->whereKey($dropped->id)->exists())->toBeFalse()
        ->and($kept->fresh()->label)->toBe('Tetap Diubah')
        ->and(evaluationAudit($form, 'Form evaluasi diubah')?->properties['old']['fields'])->toContain('Dibuang');
});

it('saves the phase-screen edit dialog, which sends neither the detail nor the fields', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationForm;

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $form), [
        'title' => 'Judul Baru',
        'description' => '',
        'is_required' => true,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($form->fresh()->title)->toBe('Judul Baru')
        ->and(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue();
});

it('refuses to move an assigned evaluation form to another phase detail', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationForm;
    $other = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $this->actingAs($this->admin)->patch(route('admin.review-evaluation-forms.update', $form), [
        'title' => $form->title,
        'form_phase_detail_id' => $other->id,
    ])->assertSessionHasErrors('form_phase_detail_id');

    expect($form->fresh()->form_phase_detail_id)->not->toBe($other->id);
});

it('deletes an unassigned evaluation form with its fields, audited', function () {
    $form = ($this->evaluation)();
    $field = ($this->field)($form, 'Rekomendasi');

    $this->actingAs($this->admin)->delete(route('admin.review-evaluation-forms.destroy', $form))
        ->assertRedirect(route('admin.review-evaluation-forms.index'));

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeFalse()
        ->and(ReviewFormField::query()->whereKey($field->id)->exists())->toBeFalse()
        ->and(evaluationAudit($form, 'Form evaluasi dihapus'))->not->toBeNull();
});

it('refuses to delete an assigned evaluation form and leaves no audit entry', function () {
    $form = evaluationFieldResponse('select', 'layak')->reviewFormField->reviewEvaluationForm;

    $this->actingAs($this->admin)->delete(route('admin.review-evaluation-forms.destroy', $form))
        ->assertSessionHasErrors('id');

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue()
        ->and(evaluationAudit($form, 'Form evaluasi dihapus'))->toBeNull();
});

it('duplicates an evaluation form with its fields and options, audited', function () {
    $form = ($this->evaluation)();
    ReviewFormFieldOption::create(['review_form_field_id' => ($this->field)($form, 'Rekomendasi')->id, 'label' => 'Layak', 'order' => 1]);

    $this->actingAs($this->admin)->post(route('admin.review-evaluation-forms.duplicate', $form));

    $copy = ReviewEvaluationForm::query()->where('title', 'Substansi (Copy)')->firstOrFail();

    expect($copy->order)->toBe(2)
        ->and($copy->reviewFormFields()->firstOrFail()->reviewFormFieldOptions()->pluck('label')->all())->toBe(['Layak'])
        ->and(evaluationAudit($copy, 'Form evaluasi diduplikasi')?->properties['new']['source_evaluation_form_id'])->toBe($form->id);
});

it('reorders evaluation forms, audited', function () {
    $form = ($this->evaluation)();

    $this->actingAs($this->admin)->post(route('admin.review-evaluation-forms.update-order'), [
        'items' => [['id' => $form->id, 'order' => 3]],
    ])->assertSessionHasNoErrors();

    expect($form->fresh()->order)->toBe(3)
        ->and(evaluationAudit($form, 'Urutan form evaluasi diubah'))->not->toBeNull();
});

it('ignores non-numeric list filters instead of failing', function () {
    ($this->evaluation)();

    $this->actingAs($this->admin)
        ->get(route('admin.review-evaluation-forms.index', ['form_phase_id' => 'semua', 'form_phase_detail_id' => 'x']))
        ->assertOk();
});

it('counts a locked answer as completed in the usage figures', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $answer->reviewFormResponse->update(['status' => 'locked', 'submitted_at' => now(), 'locked_at' => now()]);
    $form = $answer->reviewFormField->reviewEvaluationForm;

    $this->actingAs($this->admin)->get(route('admin.review-evaluation-forms.show', $form))
        ->assertInertia(fn ($page) => $page
            ->where('assignmentStats.total_assignments', 1)
            ->where('assignmentStats.completed_responses', 1)
            ->where('assignmentStats.pending_responses', 0));
});
