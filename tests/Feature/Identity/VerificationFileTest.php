<?php

// W58 — berkas biodata pendaftar dibaca Operator lewat route ber-scope, dari disk privat (W15).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\PrivateUploadDisk;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function fileVerifier(): User
{
    Permission::findOrCreate('users.verify', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.verify');

    return $operator;
}

function biodataForm(): Form
{
    $type = FormType::query()->find(FormType::BIODATA_ID)
        ?? FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);

    return Form::factory()->create(['form_type_id' => $type->id, 'is_active' => true]);
}

/** @return array{profile: UserProfile, file: FormFieldResponse, text: FormFieldResponse} */
function pendingWithBiodata(Form $form, string $nidn): array
{
    $user = User::factory()->create();
    $profile = UserProfile::factory()->pending()->create(['user_id' => $user->id, 'nidn' => $nidn]);
    $submission = FormSubmission::factory()->create(['form_id' => $form->id, 'submitted_by' => $user->id]);

    $answer = function (string $type, string $label, string $value) use ($submission): FormFieldResponse {
        $field = FormField::create([
            'form_id' => $submission->form_id,
            'field_type_id' => FieldType::firstOrCreate(['name' => $type])->id,
            'label' => $label,
            'is_required' => false,
            'order' => 1,
        ]);

        return FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => $value]);
    };

    $path = "biodata/{$nidn}.pdf";
    Storage::disk(PrivateUploadDisk::NAME)->put($path, 'isi');

    return [
        'profile' => $profile,
        'file' => $answer('file', 'Scan KTP', $path),
        'text' => $answer('text', 'Bidang Keahlian', 'Hidrologi'),
    ];
}

beforeEach(fn () => Storage::fake(PrivateUploadDisk::NAME));

it('Operator mengunduh berkas biodata pendaftar', function () {
    ['profile' => $profile, 'file' => $file] = pendingWithBiodata(biodataForm(), '0654321098');

    $this->actingAs(fileVerifier())
        ->get(route('admin.verifications.files.show', [$profile, $file]))
        ->assertOk()
        ->assertDownload('0654321098.pdf');
});

it('antrian menautkan berkas biodata tanpa jawaban non-file', function () {
    ['profile' => $profile, 'file' => $file] = pendingWithBiodata(biodataForm(), '0654321098');

    $this->actingAs(fileVerifier())
        ->get(route('admin.verifications.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pending.0.profileId', $profile->id)
            ->where('pending.0.files', [[
                'label' => 'Scan KTP',
                'url' => route('admin.verifications.files.show', [$profile, $file]),
            ]])
            ->etc());
});

it('berkas milik profil lain 404', function () {
    $form = biodataForm();
    ['profile' => $mine] = pendingWithBiodata($form, '0654321098');
    ['file' => $theirs] = pendingWithBiodata($form, '0543210987');

    $this->actingAs(fileVerifier())
        ->get(route('admin.verifications.files.show', [$mine, $theirs]))
        ->assertNotFound();
});

it('jawaban bukan file 404', function () {
    ['profile' => $profile, 'text' => $text] = pendingWithBiodata(biodataForm(), '0654321098');

    $this->actingAs(fileVerifier())
        ->get(route('admin.verifications.files.show', [$profile, $text]))
        ->assertNotFound();
});

it('berkas pengajuan non-biodata milik profil yang sama 404', function () {
    ['profile' => $profile] = pendingWithBiodata(biodataForm(), '0654321098');
    $other = FormSubmission::factory()->create(['submitted_by' => $profile->user_id]);
    $field = FormField::create([
        'form_id' => $other->form_id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'label' => 'Proposal',
        'is_required' => false,
        'order' => 1,
    ]);
    Storage::disk(PrivateUploadDisk::NAME)->put('proposal.pdf', 'isi');
    $response = FormFieldResponse::create(['form_submission_id' => $other->id, 'form_field_id' => $field->id, 'value' => 'proposal.pdf']);

    $this->actingAs(fileVerifier())
        ->get(route('admin.verifications.files.show', [$profile, $response]))
        ->assertNotFound();
});

it('peneliti 403', function () {
    ['profile' => $profile, 'file' => $file] = pendingWithBiodata(biodataForm(), '0654321098');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.verifications.files.show', [$profile, $file]))
        ->assertForbidden();
});
