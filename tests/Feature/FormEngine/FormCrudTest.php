<?php

// tests/Feature/FormEngine/FormCrudTest.php
//
// Layar Form lewat Action beraudit (BR-RPT-09): field dan opsi hanya milik form yang
// disunting (diperiksa saat terbit, BR-FE-34), hapus ditolak bila ada pengajuan (BR-FE-19).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $this->fieldType = FieldType::create(['name' => 'Pilihan '.fake()->unique()->numerify('####-####')]);
    $this->field = fn (Form $form, string $label): FormField => FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $this->fieldType->id,
        'label' => $label,
        'is_required' => false,
        'order' => 1,
    ]);
});

function formAudit(Form $form, string $description): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $form->getMorphClass())
        ->where('subject_id', $form->id)
        ->where('description', $description)
        ->first();
}

/**
 * Simpan draf field lalu terbitkan (BR-FE-34) — jalur yang menulis field ke baris hidup.
 *
 * @param  list<array<string, mixed>>  $fields
 */
function publishFormFields(User $admin, Form $form, array $fields): TestResponse
{
    test()->actingAs($admin)->put(route('admin.forms.draft.update', $form), ['fields' => $fields])->assertSessionHasNoErrors();

    return test()->actingAs($admin)->post(route('admin.forms.publish', $form));
}

it('creates a form with its fields and options, audited', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.forms.store'), [
        'title' => 'Form Baru',
        'form_type_id' => regularFormType()->id,
        'fields' => [['field_type_id' => $this->fieldType->id, 'label' => 'Skema', 'options' => [['label' => 'A']]]],
    ]);

    $form = Form::query()->where('title', 'Form Baru')->firstOrFail();
    $response->assertRedirect(route('admin.forms.edit', $form));

    // BR-FE-34 — field Form baru lahir sebagai draf; Terbitkan yang menulisnya.
    expect($form->draft()->sole()->payload['fields'][0]['options'])->toBe([['id' => null, 'label' => 'A']])
        ->and(formAudit($form, 'Formulir dibuat'))->not->toBeNull();

    publishFormFields($this->admin, $form, $form->draft()->sole()->payload['fields'])->assertSessionHasNoErrors();

    expect($form->formFields()->first()?->formFieldOptions()->pluck('label')->all())->toBe(['A'])
        ->and(formAudit($form, 'Formulir diterbitkan')?->properties['new']['fields'])->toContain('Skema');
});

it('rejects a trl_selector without a scheme_selector on the Form screen and saves nothing (BR-SCH-05)', function () {
    $trl = FieldType::firstOrCreate(['name' => 'trl_selector']);

    $this->actingAs($this->admin)->post(route('admin.forms.store'), [
        'title' => 'Form TRL',
        'form_type_id' => regularFormType()->id,
        'fields' => [['field_type_id' => $trl->id, 'label' => 'TRL', 'is_required' => true]],
    ])->assertSessionHasErrors('fields');

    expect(Form::query()->where('title', 'Form TRL')->exists())->toBeFalse();
});

it('rejects a field id that belongs to another form and leaves that form intact', function () {
    $form = Form::factory()->create();
    $other = Form::factory()->create();
    $foreign = ($this->field)($other, 'Milik Form Lain');

    publishFormFields($this->admin, $form, [
        ['id' => $foreign->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Dibajak'],
    ])->assertSessionHasErrors('fields.0.id');

    expect($foreign->fresh()?->form_id)->toBe($other->id)
        ->and($foreign->fresh()?->label)->toBe('Milik Form Lain')
        ->and($form->draft()->exists())->toBeTrue();
});

it('rejects an option id that belongs to another field', function () {
    $form = Form::factory()->create();
    $field = ($this->field)($form, 'Skema');
    $foreign = FormFieldOption::create([
        'form_field_id' => ($this->field)(Form::factory()->create(), 'Lain')->id,
        'label' => 'Opsi Lain',
    ]);

    publishFormFields($this->admin, $form, [[
        'id' => $field->id,
        'field_type_id' => $this->fieldType->id,
        'label' => 'Skema',
        'options' => [['id' => $foreign->id, 'label' => 'Dibajak']],
    ]])->assertSessionHasErrors('fields.0.options.0.id');

    expect($foreign->fresh()?->label)->toBe('Opsi Lain');
});

it('soft-deletes a field dropped from the payload and audits old and new (BR-FE-12)', function () {
    $form = Form::factory()->create();
    $kept = ($this->field)($form, 'Tetap');
    $dropped = ($this->field)($form, 'Dibuang');

    publishFormFields($this->admin, $form, [
        ['id' => $kept->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Tetap'],
    ])->assertSessionHasNoErrors();

    $audit = formAudit($form, 'Formulir diterbitkan');

    expect(FormField::withTrashed()->find($dropped->id)?->trashed())->toBeTrue()
        ->and($audit?->properties['old']['fields'])->toContain('Dibuang')
        ->and($audit?->properties['new']['fields'])->not->toContain('Dibuang');
});

it('refuses to delete a form with submissions and leaves no audit trace (BR-FE-19)', function () {
    $used = FormSubmission::factory()->create()->form;

    $this->actingAs($this->admin)->delete(route('admin.forms.destroy', $used))->assertSessionHasErrors('id');

    expect($used->fresh())->not->toBeNull()
        ->and(formAudit($used, 'Formulir dihapus'))->toBeNull();
});

it('refuses to delete a form whose access rule a published phase step uses, keeping the frozen step (BR-FE-32)', function () {
    $step = FormPhaseDetail::factory()->create();
    $form = $step->formAccessControl?->form ?? throw new LogicException('Langkah tanpa Form.');

    $this->actingAs($this->admin)->delete(route('admin.forms.destroy', $form))->assertSessionHasErrors('id');

    expect(session('errors')->first('id'))->toContain('1 langkah tahap')
        ->and($form->fresh())->not->toBeNull()
        ->and($step->fresh())->not->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.forms.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('forms.data.0.stepCount', 1));
});
