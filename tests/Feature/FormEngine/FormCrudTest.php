<?php

// tests/Feature/FormEngine/FormCrudTest.php
//
// Layar Form lewat Action beraudit (BR-RPT-09): field dan opsi hanya milik form yang
// disunting, hapus massal semua-atau-tidak (BR-FE-19), parameter daftar dibatasi whitelist.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSubmission;
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

/** @param  list<array<string, mixed>>  $fields */
function formPayload(Form $form, array $fields): array
{
    return ['title' => 'Form Disunting', 'form_type_id' => $form->form_type_id, 'fields' => $fields];
}

it('creates a form with its fields and options, audited', function () {
    $type = Form::factory()->create()->form_type_id;

    $this->actingAs($this->admin)->post(route('admin.forms.store'), [
        'title' => 'Form Baru',
        'form_type_id' => $type,
        'fields' => [['field_type_id' => $this->fieldType->id, 'label' => 'Skema', 'options' => [['label' => 'A']]]],
    ])->assertRedirect(route('admin.forms.index'));

    $form = Form::query()->where('title', 'Form Baru')->firstOrFail();

    expect($form->formFields()->first()?->formFieldOptions()->pluck('label')->all())->toBe(['A'])
        ->and(formAudit($form, 'Formulir dibuat')?->properties['new']['fields'])->toContain('Skema');
});

it('rejects a field id that belongs to another form and leaves that form intact', function () {
    $form = Form::factory()->create();
    $other = Form::factory()->create();
    $foreign = ($this->field)($other, 'Milik Form Lain');

    $this->actingAs($this->admin)->put(route('admin.forms.update', $form), formPayload($form, [
        ['id' => $foreign->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Dibajak'],
    ]))->assertSessionHasErrors('fields.0.id');

    expect($foreign->fresh()?->form_id)->toBe($other->id)
        ->and($foreign->fresh()?->label)->toBe('Milik Form Lain')
        ->and($form->fresh()?->title)->not->toBe('Form Disunting');
});

it('rejects an option id that belongs to another field', function () {
    $form = Form::factory()->create();
    $field = ($this->field)($form, 'Skema');
    $foreign = FormFieldOption::create([
        'form_field_id' => ($this->field)(Form::factory()->create(), 'Lain')->id,
        'label' => 'Opsi Lain',
    ]);

    $this->actingAs($this->admin)->put(route('admin.forms.update', $form), formPayload($form, [[
        'id' => $field->id,
        'field_type_id' => $this->fieldType->id,
        'label' => 'Skema',
        'options' => [['id' => $foreign->id, 'label' => 'Dibajak']],
    ]]))->assertSessionHasErrors('fields.0.options.0.id');

    expect($foreign->fresh()?->label)->toBe('Opsi Lain');
});

it('soft-deletes a field dropped from the payload and audits old and new (BR-FE-12)', function () {
    $form = Form::factory()->create();
    $kept = ($this->field)($form, 'Tetap');
    $dropped = ($this->field)($form, 'Dibuang');

    $this->actingAs($this->admin)->put(route('admin.forms.update', $form), formPayload($form, [
        ['id' => $kept->id, 'field_type_id' => $this->fieldType->id, 'label' => 'Tetap'],
    ]))->assertRedirect(route('admin.forms.index'));

    $audit = formAudit($form, 'Formulir diubah');

    expect(FormField::withTrashed()->find($dropped->id)?->trashed())->toBeTrue()
        ->and($audit?->properties['old']['fields'])->toContain('Dibuang')
        ->and($audit?->properties['new']['fields'])->not->toContain('Dibuang');
});

it('deletes nothing in a bulk delete when one form has submissions (BR-FE-19)', function () {
    $unused = Form::factory()->create();
    $used = FormSubmission::factory()->create()->form;

    $this->actingAs($this->admin)
        ->post(route('admin.forms.bulk-delete'), ['ids' => [$unused->id, $used->id]])
        ->assertSessionHasErrors('ids');

    expect(session('errors')->first('ids'))->toContain('Tidak ada yang dihapus', $used->title)
        ->and(Form::query()->whereKey([$unused->id, $used->id])->count())->toBe(2)
        ->and(formAudit($unused, 'Formulir dihapus'))->toBeNull();
});

it('bulk deletes unused forms with one audit entry each', function () {
    $forms = Form::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.forms.bulk-delete'), ['ids' => $forms->modelKeys()])
        ->assertSessionHas('success', '2 formulir berhasil dihapus!');

    expect(Form::query()->whereKey($forms->modelKeys())->count())->toBe(0)
        ->and(formAudit($forms[0], 'Formulir dihapus'))->not->toBeNull()
        ->and(formAudit($forms[1], 'Formulir dihapus'))->not->toBeNull();
});

it('refuses to delete a form with submissions and leaves no audit trace (BR-FE-19)', function () {
    $used = FormSubmission::factory()->create()->form;

    $this->actingAs($this->admin)->delete(route('admin.forms.destroy', $used))->assertSessionHasErrors('id');

    expect($used->fresh())->not->toBeNull()
        ->and(formAudit($used, 'Formulir dihapus'))->toBeNull();
});

it('duplicates live fields and options only, audited with its source', function () {
    $form = Form::factory()->create();
    $field = ($this->field)($form, 'Aktif');
    ($this->field)($form, 'Terhapus')->delete();
    FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Opsi Aktif']);
    FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Opsi Terhapus'])->delete();

    $this->actingAs($this->admin)->post(route('admin.forms.duplicate', $form))->assertRedirect();

    $copy = Form::query()->where('title', $form->title.' (Copy)')->firstOrFail();

    expect($copy->formFields()->pluck('label')->all())->toBe(['Aktif'])
        ->and($copy->formFields()->first()?->formFieldOptions()->pluck('label')->all())->toBe(['Opsi Aktif'])
        ->and(formAudit($copy, 'Formulir diduplikasi')?->properties['new']['source_form_id'])->toBe($form->id);
});

it('falls back to defaults for unknown list parameters instead of failing', function () {
    Form::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.forms.index', ['sort_by' => 'nope', 'sort_order' => 'sideways', 'per_page' => 'x']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort_by', 'created_at')
            ->where('filters.sort_order', 'desc')
            ->where('filters.per_page', 10));
});

it('sorts by form type and filters by active state', function () {
    Form::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('admin.forms.index', ['sort_by' => 'form_type', 'is_active' => 'inactive']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('forms.total', 1));
});
