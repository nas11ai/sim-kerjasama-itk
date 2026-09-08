<?php

// tests/Feature/BiodataFormPayloadTest.php
//
// FormField::$helper_text dan FormFieldOption::$value tidak pernah ada di
// migration manapun — keduanya selalu null. BiodataController::showBiodataForm()
// tetap mengirim kedua field itu ke frontend, dan IndexPage.vue mem-bind
// :value="option.value" untuk setiap <SelectItem>/<RadioGroupItem> —
// akibatnya setiap select/radio di form Biodata selalu mengirim value
// kosong. Test pertama memastikan payload berhenti membawa kunci hantu
// tersebut. Frontend dibindkan ke option.id.toString() sebagai gantinya,
// mengikuti konvensi yang sudah dipakai FormPhase.vue/ShowSubmission.vue
// untuk form_field_options + form_field_responses.value yang sama
// (BR-FE-03 — FormFieldResponse hanya menyimpan scalar values). Test kedua
// mengunci konvensi id itu di jalur submit.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
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

    // User belum pernah submit biodata sama sekali ($submission null) —
    // showBiodataForm() harus tetap jalan lewat $submission?->status yang
    // null-safe, bukan dikondisikan pakai fixture submission palsu.
    $this->actingAs($user)
        ->get(route('user.biodata.index'))
        ->assertInertia(fn (Assert $page) => $page
            // shouldExist=false: config inertia.pages.paths default paket
            // ('js/pages') tidak cocok dengan struktur direktori repo ini
            // ('js/Pages'), jadi cek eksistensi file selalu gagal terlepas
            // dari bug yang diuji.
            ->component('User/Biodata/IndexPage')
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

it('stores the selected option id, not its label, as the response value for select and radio fields', function () {
    // Konvensi ini sudah dipakai FormPhase.vue (general submission flow) dan
    // dibaca balik oleh ShowSubmission.vue lewat
    // form_field_options.find((opt) => opt.id.toString() === value) — form
    // Biodata memakai form_field_responses.value yang sama, jadi harus ikut
    // konvensi yang sama: value yang disimpan adalah option id, bukan label.
    $organization = Organization::create(['name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'), 'type' => 'study_program']);
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    // form_type_id = 1 hardcoded di BiodataController; lihat catatan di
    // test di atas.
    $formType = FormType::forceCreate(['id' => 1, 'name' => 'Biodata']);
    $form = Form::factory()->create(['form_type_id' => $formType->id, 'is_active' => true]);
    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);

    $selectFieldType = FieldType::create(['name' => 'select']);
    $selectField = FormField::create([
        'form_id' => $form->id,
        'label' => 'Jenis Kelamin',
        'is_required' => false,
        'field_type_id' => $selectFieldType->id,
        'order' => 1,
    ]);
    $selectOption = FormFieldOption::create(['form_field_id' => $selectField->id, 'label' => 'Laki-laki']);
    FormFieldOption::create(['form_field_id' => $selectField->id, 'label' => 'Perempuan']);

    $radioFieldType = FieldType::create(['name' => 'radio']);
    $radioField = FormField::create([
        'form_id' => $form->id,
        'label' => 'Status Pernikahan',
        'is_required' => false,
        'field_type_id' => $radioFieldType->id,
        'order' => 2,
    ]);
    $radioOption = FormFieldOption::create(['form_field_id' => $radioField->id, 'label' => 'Menikah']);
    FormFieldOption::create(['form_field_id' => $radioField->id, 'label' => 'Belum Menikah']);

    // Payload ini merepresentasikan apa yang dikirim IndexPage.vue setelah
    // perbaikan: :value="option.id.toString()" pada SelectItem/RadioGroupItem.
    //
    // is_submitted sengaja false (draft save): penyimpanan value berjalan
    // identik untuk draft maupun submit final, dan jalur submit final
    // sudah punya test sendiri di BiodataSubmissionStatusTest.
    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => false,
            'responses' => [
                ['form_field_id' => $selectField->id, 'value' => (string) $selectOption->id],
                ['form_field_id' => $radioField->id, 'value' => (string) $radioOption->id],
            ],
        ])
        ->assertRedirect(route('user.dashboard'));

    $submission = FormSubmission::where('form_id', $form->id)
        ->where('submitted_by', $user->id)
        ->firstOrFail();

    $storedSelectValue = FormFieldResponse::where('form_submission_id', $submission->id)
        ->where('form_field_id', $selectField->id)
        ->value('value');

    $storedRadioValue = FormFieldResponse::where('form_submission_id', $submission->id)
        ->where('form_field_id', $radioField->id)
        ->value('value');

    expect($storedSelectValue)->toBe((string) $selectOption->id)
        ->and($storedSelectValue)->not->toBe($selectOption->label);

    expect($storedRadioValue)->toBe((string) $radioOption->id)
        ->and($storedRadioValue)->not->toBe($radioOption->label);
});
