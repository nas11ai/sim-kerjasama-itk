<?php

// tests/Feature/Review/EvaluationFormCrudTest.php
//
// Pustaka Formulir Evaluasi lewat Action beraudit (BR-RPT-09). BR-FE-31 — isi disunting sebagai draf;
// BR-REV-18 — Terbitkan menyalin baris field, jadi versi lama dan jawaban reviewer tidak tersentuh.

use App\Actions\Review\SaveEvaluationFormDraft;
use App\Data\Request\EvaluationDraftInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Models\ConfigurationDraft;
use App\Models\FieldType;
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
});

function evaluationAudit(ReviewEvaluationForm $form, string $description): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $form->getMorphClass())
        ->where('subject_id', $form->id)
        ->where('description', $description)
        ->first();
}

/** Simpan draf `$fields` lewat route lalu terbitkan. */
function draftAndPublish(ReviewEvaluationForm $form, array $fields): void
{
    test()->put(route('admin.evaluation-forms.draft.update', $form), ['fields' => $fields])->assertSessionHasNoErrors();
    test()->post(route('admin.evaluation-forms.publish', $form));
}

it('creates an evaluation form without a description, drafts and publishes its fields and options, audited', function () {
    $this->actingAs($this->admin)->post(route('admin.evaluation-forms.store'), ['title' => 'Kelayakan'])
        ->assertSessionHasNoErrors();
    $form = ReviewEvaluationForm::query()->where('title', 'Kelayakan')->firstOrFail();

    draftAndPublish($form, [[
        'field_type_id' => $this->fieldType->id,
        'label' => 'Rekomendasi',
        'validation_rules' => ['max_length' => 500],
        'options' => [['label' => 'Layak', 'value' => 'layak']],
    ]]);

    $field = $form->fresh()?->latestVersion?->reviewFormFields()->firstOrFail();
    expect($form->description)->toBeNull()
        ->and($form->fresh()?->latestVersion?->label())->toBe('Versi 1')
        ->and($field?->validation_rules)->toBe(['max_length' => 500])
        ->and($field?->reviewFormFieldOptions()->pluck('value')->all())->toBe(['layak'])
        ->and(evaluationAudit($form, 'Form evaluasi dibuat'))->not->toBeNull()
        ->and(evaluationAudit($form, 'Draf form evaluasi disimpan'))->not->toBeNull()
        ->and(evaluationAudit($form, 'Form evaluasi diterbitkan')?->properties['new']['fields'])->toBe(['Rekomendasi']);
});

it('ignores field and option ids sent in a draft, leaving other forms intact', function () {
    $other = evaluationFieldResponse('select', 'layak')->reviewFormField;
    $option = ReviewFormFieldOption::create(['review_form_field_id' => $other->id, 'label' => 'Asli', 'order' => 1]);
    $form = ReviewEvaluationForm::create(['title' => 'Substansi']);

    $this->actingAs($this->admin);
    draftAndPublish($form, [[
        'id' => $other->id,
        'field_type_id' => $this->fieldType->id,
        'label' => 'Dibajak',
        'options' => [['id' => $option->id, 'label' => 'Dibajak']],
    ]]);

    expect($other->fresh()?->label)->not->toBe('Dibajak')
        ->and($option->fresh()?->label)->toBe('Asli')
        ->and($form->fresh()?->latestVersion?->reviewFormFields()->pluck('label')->all())->toBe(['Dibajak']);
});

it('refuses to publish a draft without fields, keeping the draft and the answers', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationFormVersion->reviewEvaluationForm;
    SaveEvaluationFormDraft::run($form, new EvaluationDraftInput([]), $this->admin);

    $this->actingAs($this->admin)->post(route('admin.evaluation-forms.publish', $form))
        ->assertSessionHasErrors(['fields' => 'Form evaluasi butuh minimal satu field.']);

    expect(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue()
        ->and($form->versions()->count())->toBe(1)
        ->and(ConfigurationDraft::query()->count())->toBe(1);
});

it('drops an option in the next version while old answers still read its label', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $field = $answer->reviewFormField;
    ReviewFormFieldOption::create(['review_form_field_id' => $field->id, 'label' => 'Layak', 'value' => 'layak', 'order' => 1]);
    $form = $field->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin);
    draftAndPublish($form, [['field_type_id' => $field->field_type_id, 'label' => 'Uji', 'options' => []]]);

    expect($form->fresh()?->latestVersion?->reviewFormFields()->sole()->reviewFormFieldOptions()->count())->toBe(0)
        ->and($field->reviewFormFieldOptions()->count())->toBe(1)
        ->and($answer->fresh()?->formatted_value)->toBe('Layak');
});

it('publishes revised fields as the next version, keeping the old version, audited', function () {
    $form = ReviewEvaluationForm::create(['title' => 'Substansi']);
    publishVersion($form, [['type' => 'select', 'label' => 'Tetap'], ['type' => 'select', 'label' => 'Dibuang']]);
    $kept = $form->latestVersion?->reviewFormFields()->firstOrFail();

    $this->actingAs($this->admin);
    draftAndPublish($form, [['field_type_id' => $this->fieldType->id, 'label' => 'Tetap Diubah']]);

    $versions = $form->versions()->get();
    expect($versions->pluck('number')->all())->toBe([1, 2])
        ->and($versions[0]->reviewFormFields()->pluck('label')->all())->toBe(['Tetap', 'Dibuang'])
        ->and($versions[1]->reviewFormFields()->pluck('label')->all())->toBe(['Tetap Diubah'])
        ->and($kept?->fresh()?->label)->toBe('Tetap')
        ->and(evaluationAudit($form, 'Form evaluasi diterbitkan')?->properties['new'])->toBe(['number' => 2, 'fields' => ['Tetap Diubah']]);
});

it('renames a form without touching its versions or answers, audited', function () {
    $answer = evaluationFieldResponse('select', 'layak');
    $form = $answer->reviewFormField->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin)->put(route('admin.evaluation-forms.update', $form), [
        'title' => 'Judul Baru',
        'description' => '',
    ])->assertSessionHasNoErrors();

    expect($form->fresh()?->title)->toBe('Judul Baru')
        ->and($form->versions()->count())->toBe(1)
        ->and(ReviewFormFieldResponse::query()->whereKey($answer->id)->exists())->toBeTrue()
        ->and(evaluationAudit($form, 'Form evaluasi diubah'))->not->toBeNull();
});

it('deletes an unattached evaluation form with its versions and fields, audited', function () {
    $form = ReviewEvaluationForm::create(['title' => 'Substansi']);
    $field = publishVersion($form, [['type' => 'select', 'label' => 'Rekomendasi']])->reviewFormFields()->firstOrFail();

    $this->actingAs($this->admin)->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertRedirect(route('admin.evaluation-forms.index'))
        ->assertSessionHas('success', 'Form evaluasi dihapus.');

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeFalse()
        ->and(ReviewFormField::query()->whereKey($field->id)->exists())->toBeFalse()
        ->and(evaluationAudit($form, 'Form evaluasi dihapus'))->not->toBeNull();
});

it('refuses to delete an assigned evaluation form and leaves no audit entry', function () {
    $form = evaluationFieldResponse('select', 'layak')->reviewFormField->reviewEvaluationFormVersion->reviewEvaluationForm;

    $this->actingAs($this->admin)->delete(route('admin.evaluation-forms.destroy', $form))
        ->assertSessionHasErrors('id');

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue()
        ->and(evaluationAudit($form, 'Form evaluasi dihapus'))->toBeNull();
});

it('wewenang hapus: operator 403', function () {
    $form = ReviewEvaluationForm::create(['title' => 'Substansi']);
    Role::findOrCreate('Operator');
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $this->actingAs($operator)->delete(route('admin.evaluation-forms.destroy', $form))->assertForbidden();

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue();
});

it('round-trips a draft payload into the same field inputs', function () {
    $input = new EvaluationDraftInput([
        new ReviewFormFieldInput(null, 7, 'Skor', 'Skala 1–7', true, ['min_value' => 1.0, 'max_value' => 7.0], null),
        new ReviewFormFieldInput(null, 8, 'Rekomendasi', null, false, null, [['id' => null, 'label' => 'Layak', 'value' => 'layak']]),
    ]);

    expect(EvaluationDraftInput::fromPayload($input->toPayload()))->toEqual($input);
});
