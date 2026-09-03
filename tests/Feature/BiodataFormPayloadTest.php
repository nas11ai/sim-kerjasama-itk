<?php

// tests/Feature/BiodataFormPayloadTest.php
//
// FormField::$helper_text dan FormFieldOption::$value tidak pernah ada di
// migration manapun — keduanya selalu null. BiodataController::showBiodataForm()
// tetap mengirim kedua field itu ke frontend, dan IndexPage.vue mem-bind
// :value="option.value" untuk setiap <SelectItem>/<RadioGroupItem> —
// akibatnya setiap select/radio di form Biodata selalu mengirim value
// kosong. Test ini memastikan payload berhenti membawa kunci hantu
// tersebut; frontend dibindkan ke option.label sebagai gantinya
// (BR-FE-03 — FormFieldResponse hanya menyimpan scalar values).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

it('does not send phantom helper_text or option value keys in the biodata form payload', function () {
    $organization = Organization::create(['name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'), 'type' => 'study_program']);
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    // form_type_id = 1 hardcoded di BiodataController; FormTypeSeeder
    // menaruh "Biodata" sebagai baris pertama sehingga id-nya 1 di
    // production. Paksa id yang sama di sini supaya query controller
    // menemukan form ini tanpa bergantung pada urutan test lain.
    $formType = FormType::forceCreate(['id' => 1, 'name' => 'Biodata']);
    $form = Form::factory()->create(['form_type_id' => $formType->id, 'is_active' => true]);
    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $fieldType = FieldType::create(['name' => 'select']);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Jenis Kelamin',
        'is_required' => true,
        'field_type_id' => $fieldType->id,
        'order' => 1,
    ]);
    FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Laki-laki']);

    // Draft submission supaya showBiodataForm() melewati cabang "belum
    // pernah submit" — tidak relevan untuk bug yang diuji di sini.
    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.biodata.index'))
        ->assertInertia(fn (Assert $page) => $page
            // shouldExist=false: config inertia.pages.paths default paket
            // ('js/pages') tidak cocok dengan struktur direktori repo ini
            // ('js/Pages'), jadi cek eksistensi file selalu gagal terlepas
            // dari bug yang diuji.
            ->component('User/Biodata/IndexPage', false)
            ->has('form.form_fields', 1)
            ->has('form.form_fields.0', fn (Assert $field) => $field
                ->missing('helper_text')
                ->has('id')
                ->has('label')
                ->has('is_required')
                ->has('order')
                ->has('field_type')
                ->has('form_field_options', 1)
                ->has('form_field_options.0', fn (Assert $option) => $option
                    ->missing('value')
                    ->has('id')
                    ->where('label', 'Laki-laki')
                )
            )
        );
});
