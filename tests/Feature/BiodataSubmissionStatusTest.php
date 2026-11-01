<?php

// tests/Feature/BiodataSubmissionStatusTest.php
//
// BiodataController::submitForm() dulu menulis 'status' => 'pending' sebagai
// string mentah ke FormSubmission::$status, padahal kolom itu dikelola
// spatie/laravel-model-states dan 'pending' bukan state yang terdaftar di
// SubmissionStatus::config() — setiap submit final (is_submitted=true)
// melempar Spatie\ModelStates\Exceptions\UnknownState. Satu-satunya transisi
// keluar dari Draft adalah Draft->Submitted, jadi Submitted adalah satu-
// satunya state yang sah begitu is_submitted=true (BR-SM-03 — status hanya
// berpindah lewat transitionTo()).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Spatie\Permission\Models\Permission;

function setUpBiodataForm(): array
{
    $organization = Organization::create(['name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'), 'type' => 'study_program']);
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    // Kode mencari form biodata lewat FormType::BIODATA_ID (=1; FormTypeSeeder
    // menaruh "Biodata" sebagai baris pertama). Paksa id yang sama di sini,
    // mengikuti konvensi di BiodataFormPayloadTest.php.
    $formType = FormType::forceCreate(['id' => 1, 'name' => 'Biodata']);
    $form = Form::factory()->create(['form_type_id' => $formType->id, 'is_active' => true]);
    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);

    // Field tidak wajib — hanya supaya 'responses' => 'required|array' lolos
    // validasi dengan payload yang tidak kosong. Kelengkapan field wajib
    // bukan yang diuji di sini.
    $fieldType = FieldType::create(['name' => 'text']);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Nama Lengkap',
        'is_required' => false,
        'field_type_id' => $fieldType->id,
        'order' => 1,
    ]);

    return [$user, $form, $field];
}

it('leaves the submission in Draft when saved without submitting', function () {
    [$user, $form, $field] = setUpBiodataForm();

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => false,
            'responses' => [
                ['form_field_id' => $field->id, 'value' => 'Budi'],
            ],
        ])
        ->assertRedirect(route('dashboard'));

    $submission = FormSubmission::where('form_id', $form->id)
        ->where('submitted_by', $user->id)
        ->firstOrFail();

    expect($submission->status)->toBeInstanceOf(Draft::class);
});

it('transitions the submission to Submitted when submitted as final', function () {
    [$user, $form, $field] = setUpBiodataForm();

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => true,
            'responses' => [
                ['form_field_id' => $field->id, 'value' => 'Budi'],
            ],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    $submission = FormSubmission::where('form_id', $form->id)
        ->where('submitted_by', $user->id)
        ->firstOrFail();

    expect($submission->status)->toBeInstanceOf(Submitted::class);
});

it('does not throw when an already-submitted biodata is submitted again', function () {
    [$user, $form, $field] = setUpBiodataForm();

    $payload = [
        'form_id' => $form->id,
        'is_submitted' => true,
        'responses' => [
            ['form_field_id' => $field->id, 'value' => 'Budi'],
        ],
    ];

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), $payload)
        ->assertRedirect(route('dashboard'));

    // Submit ulang biodata yang statusnya sudah Submitted — updateOrCreate()
    // di submitForm() menemukan baris yang sama (form_id + submitted_by) dan
    // mencoba transisi lagi. Tanpa guard, ini adalah transisi ilegal
    // Submitted->Submitted karena SubmissionStatus::config() hanya
    // mengizinkan Draft->Submitted.
    $this->actingAs($user)
        ->post(route('user.biodata.submit'), $payload)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    $submission = FormSubmission::where('form_id', $form->id)
        ->where('submitted_by', $user->id)
        ->firstOrFail();

    expect($submission->status)->toBeInstanceOf(Submitted::class);
});
