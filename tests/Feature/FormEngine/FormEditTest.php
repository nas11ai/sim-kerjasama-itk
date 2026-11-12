<?php

// ADMIN-08-06 — kontrak tab General & Fields (mode buat dan sunting), Biodata (W80),
// arah redirect (W86), dan wewenang `schemes.manage` (W88).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->regularType = regularFormType();
    $this->biodataType = biodataFormType();
    $this->textType = FieldType::firstOrCreate(['name' => 'text']);
    $this->selectType = FieldType::firstOrCreate(['name' => 'select']);
});

function formEditActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array<string, mixed> */
function formEditPayload(int $typeId, array $overrides = []): array
{
    return [
        'title' => 'Form Proposal Penelitian',
        'description' => null,
        'form_type_id' => $typeId,
        'is_active' => true,
        'fields' => [],
        ...$overrides,
    ];
}

function formEditField(Form $form, FieldType $type, string $label, int $order = 1): FormField
{
    return FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $type->id,
        'label' => $label,
        'is_required' => true,
        'order' => $order,
    ]);
}

it('kontrak mode buat', function () {
    $this->actingAs(formEditActor())
        ->get(route('admin.forms.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/FormEditPage')
            ->where('tab', 'general')
            ->where('form', null)
            ->where('general.title', '')
            ->where('general.isActive', true)
            ->where('general.fields', [])
            ->where('general.archivedFields', [])
            ->where('general.hasDraft', false)
            ->where('general.publishedAt', null)
            ->where('general.impact', ['newRequired' => 0, 'draftOwners' => 0, 'relabelled' => [], 'removed' => 0])
            ->has('fieldTypeOptions.0', fn (AssertableInertia $option) => $option
                ->hasAll(['value', 'name', 'label', 'hasOptions', 'limitType']))
            ->has('formTypeOptions'));
});

it('kontrak mode sunting', function () {
    $form = Form::factory()->create(['form_type_id' => $this->regularType->id]);
    $second = formEditField($form, $this->textType, 'Abstrak', 2);
    $first = formEditField($form, $this->selectType, 'Skema', 1);
    FormFieldOption::create(['form_field_id' => $first->id, 'label' => 'Internal']);
    FormFieldOption::create(['form_field_id' => $first->id, 'label' => 'Dibuang'])->delete();
    formEditField($form, $this->textType, 'Lokasi Penelitian (lama)', 3)->delete();

    $this->actingAs(formEditActor())
        ->get(route('admin.forms.edit', $form))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/FormEditPage')
            ->where('tab', 'general')
            ->where('form', ['id' => $form->id, 'title' => $form->title, 'isActive' => true])
            ->where('general.title', $form->title)
            ->where('general.formTypeId', $this->regularType->id)
            ->has('general.fields', 2)
            ->has('general.fields.0', fn (AssertableInertia $field) => $field
                ->where('id', $first->id)
                ->where('label', 'Skema')
                ->where('fieldTypeId', $this->selectType->id)
                ->where('isRequired', true)
                ->where('requiredSince', $first->fresh()?->required_since?->toIso8601String())
                ->where('answerCount', 0)
                ->where('options', [['id' => $first->formFieldOptions()->sole()->id, 'label' => 'Internal']]))
            ->where('general.fields.1.id', $second->id)
            // FE-CHANGE-01 / BR-FE-12 — field terhapus diarsipkan dan tetap terlihat.
            ->where('general.archivedFields', [['id' => FormField::onlyTrashed()->sole()->id, 'label' => 'Lokasi Penelitian (lama)', 'typeName' => 'Teks']]));
});

it('answerCount menghitung jawaban field', function () {
    $form = Form::factory()->create(['form_type_id' => $this->regularType->id]);
    $field = formEditField($form, $this->textType, 'Judul Penelitian');
    FormFieldResponse::create([
        'form_submission_id' => FormSubmission::factory()->create(['form_id' => $form->id])->id,
        'form_field_id' => $field->id,
        'value' => 'Isi',
    ]);

    $this->actingAs(formEditActor())
        ->get(route('admin.forms.edit', $form))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('general.fields.0.answerCount', 1));
});

it('Biodata tidak ditawarkan untuk form baru', function () {
    $this->actingAs(formEditActor())
        ->get(route('admin.forms.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('formTypeOptions', [['value' => (string) $this->regularType->id, 'label' => $this->regularType->name]]));

    $this->actingAs(formEditActor())
        ->postJson(route('admin.forms.store'), formEditPayload($this->biodataType->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('form_type_id');
});

it('Biodata hanya bertahan pada Form yang sudah bertipe Biodata', function () {
    $other = Form::factory()->create(['form_type_id' => $this->regularType->id]);
    $biodata = Form::factory()->create(['form_type_id' => $this->biodataType->id]);
    $actor = formEditActor();

    $this->actingAs($actor)
        ->putJson(route('admin.forms.update', $other), formEditPayload($this->biodataType->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('form_type_id');

    $this->actingAs($actor)
        ->put(route('admin.forms.update', $biodata), formEditPayload($this->biodataType->id))
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    $this->actingAs($actor)
        ->get(route('admin.forms.edit', $biodata))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('formTypeOptions', 2));

    expect($other->fresh()?->form_type_id)->toBe($this->regularType->id)
        ->and($biodata->fresh()?->form_type_id)->toBe($this->biodataType->id);
});

it('store mengarah ke halaman sunting form baru', function () {
    $this->actingAs(formEditActor())
        ->post(route('admin.forms.store'), formEditPayload($this->regularType->id))
        ->assertRedirect(route('admin.forms.edit', Form::query()->sole()))
        ->assertSessionHas('success', 'Form dibuat. Susun field-nya lalu terbitkan.');
});

it('update tanpa continue tetap di editor', function () {
    $form = Form::factory()->create(['form_type_id' => $this->regularType->id]);
    $actor = formEditActor();

    $this->actingAs($actor)
        ->from(route('admin.forms.edit', $form))
        ->put(route('admin.forms.update', $form), formEditPayload($this->regularType->id))
        ->assertRedirect(route('admin.forms.edit', $form))
        ->assertSessionHas('success', 'Form diperbarui.');

    // W86 — "Lanjut" menyimpan lalu membuka tab Access Control.
    $this->actingAs($actor)
        ->put(route('admin.forms.update', $form), formEditPayload($this->regularType->id, ['continue' => true]))
        ->assertRedirect(route('admin.forms.access.index', $form));
});

it('wewenang: operator 403 untuk create, store, edit, update', function () {
    $form = Form::factory()->create(['form_type_id' => $this->regularType->id]);
    $operator = formEditActor('operator');
    $payload = formEditPayload($this->regularType->id);

    $this->actingAs($operator)->get(route('admin.forms.create'))->assertForbidden();
    $this->actingAs($operator)->post(route('admin.forms.store'), $payload)->assertForbidden();
    $this->actingAs($operator)->get(route('admin.forms.edit', $form))->assertForbidden();
    $this->actingAs($operator)->put(route('admin.forms.update', $form), $payload)->assertForbidden();

    expect(Form::query()->count())->toBe(1);
});

it('PUT tanpa is_active mempertahankan status Form nonaktif', function () {
    $form = Form::factory()->create(['form_type_id' => $this->regularType->id, 'is_active' => false]);
    $payload = formEditPayload($this->regularType->id);
    unset($payload['is_active']);

    $this->actingAs(formEditActor())->put(route('admin.forms.update', $form), $payload)->assertRedirect();

    expect($form->fresh()->is_active)->toBeFalse();
});
