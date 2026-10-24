<?php

// tests/Feature/Identity/SaveBiodataTest.php
//
// Jalur tolak SaveBiodata dan layar biodata yang belum dikunci
// BiodataSubmissionStatusTest / BiodataFormPayloadTest.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array{User, Form, FormField} */
function biodataFixture(bool $grantAccess = true, string $fieldType = 'text'): array
{
    $organization = Organization::create(['name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'), 'type' => 'study_program']);
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    $formType = FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);
    $form = Form::factory()->create(['form_type_id' => $formType->id, 'is_active' => true]);

    if ($grantAccess) {
        FormAccessControl::create([
            'form_id' => $form->id,
            'permission' => 'submissions.create',
            'organization_id' => $organization->id,
        ]);
    }

    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Isian Wajib',
        'is_required' => true,
        'field_type_id' => FieldType::create(['name' => $fieldType])->id,
        'order' => 1,
    ]);

    return [$user, $form, $field];
}

it('rejects a final submission that leaves a required field blank', function () {
    [$user, $form, $field] = biodataFixture();

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => true,
            'responses' => [['form_field_id' => $field->id, 'value' => '']],
        ])
        ->assertSessionHasErrors('field_'.$field->id);

    expect(FormSubmission::where('form_id', $form->id)->exists())->toBeFalse();
});

it('lets a draft leave a required field blank', function () {
    [$user, $form, $field] = biodataFixture();

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => false,
            'responses' => [['form_field_id' => $field->id, 'value' => '']],
        ])
        ->assertRedirect(route('user.dashboard'));

    $submission = FormSubmission::where('form_id', $form->id)->firstOrFail();

    expect($submission->formFieldResponses()->count())->toBe(0);
});

it('stores an uploaded file as the answer of a required file field', function () {
    Storage::fake('public');
    [$user, $form, $field] = biodataFixture(fieldType: 'file');

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => true,
            'responses' => [['form_field_id' => $field->id, 'value' => '']],
            'file_uploads' => [$field->id => UploadedFile::fake()->create('ktp.pdf', 100)],
        ])
        ->assertRedirect(route('user.dashboard'));

    $path = FormSubmission::where('form_id', $form->id)->firstOrFail()
        ->formFieldResponses()->where('form_field_id', $field->id)->value('value');

    expect($path)->toStartWith('biodata-uploads/');
    Storage::disk('public')->assertExists($path);
});

it('rejects an upload larger than 10MB', function () {
    Storage::fake('public');
    [$user, $form, $field] = biodataFixture(fieldType: 'file');

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => false,
            'responses' => [['form_field_id' => $field->id, 'value' => '']],
            'file_uploads' => [$field->id => UploadedFile::fake()->create('besar.pdf', 10 * 1024 + 1)],
        ])
        ->assertSessionHasErrors('file_uploads.'.$field->id);

    expect(FormSubmission::where('form_id', $form->id)->exists())->toBeFalse();
});

it('forbids saving biodata on a form the user has no access to', function () {
    [$user, $form, $field] = biodataFixture(grantAccess: false);

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => false,
            'responses' => [['form_field_id' => $field->id, 'value' => 'Budi']],
        ])
        ->assertForbidden();

    expect(FormSubmission::where('form_id', $form->id)->exists())->toBeFalse();
});

it('records an audit entry when biodata is saved', function () {
    [$user, $form, $field] = biodataFixture();

    $this->actingAs($user)->post(route('user.biodata.submit'), [
        'form_id' => $form->id,
        'is_submitted' => true,
        'responses' => [['form_field_id' => $field->id, 'value' => 'Budi']],
    ]);

    $this->assertDatabaseHas('activity_log', ['description' => 'Biodata dikirim', 'causer_id' => $user->id]);
});

it('lets the user edit only a draft, rejected or revision-needed biodata', function (string $state, bool $canEdit) {
    [$user, $form] = biodataFixture();
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
        'status' => $state,
    ]);

    $this->actingAs($user)
        ->get(route('user.biodata.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Biodata/IndexPage')
            ->where('existingSubmission.status', $submission->status->key())
            ->where('canEdit', $canEdit)
        );
})->with([
    'draft' => [Draft::class, true],
    'rejected' => [Rejected::class, true],
    'needs revision' => [NeedsRevision::class, true],
    'under review' => [UnderReview::class, false],
]);

it('sends the user back to the dashboard when no biodata form is active', function () {
    [$user, $form] = biodataFixture();
    $form->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('user.biodata.index'))
        ->assertRedirect(route('user.dashboard'))
        ->assertSessionHas('error');
});

it('resubmits a biodata that needs revision', function () {
    [$user, $form, $field] = biodataFixture();
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
        'status' => NeedsRevision::class,
    ]);

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => true,
            'responses' => [['form_field_id' => $field->id, 'value' => 'Budi Revisi']],
        ])
        ->assertRedirect(route('user.dashboard'))
        ->assertSessionHas('success');

    expect($submission->fresh()->status)->toBeInstanceOf(Resubmitted::class)
        ->and(FormSubmission::where('form_id', $form->id)->count())->toBe(1);
});

it('starts a fresh biodata after a rejection and keeps the rejected one as history', function () {
    [$user, $form, $field] = biodataFixture();
    $rejected = FormSubmission::create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
        'status' => Rejected::class,
    ]);
    FormFieldResponse::create(['form_submission_id' => $rejected->id, 'form_field_id' => $field->id, 'value' => 'Lama']);

    $this->actingAs($user)
        ->get(route('user.biodata.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', true)
            ->where('existingResponses', [])
        );

    $this->actingAs($user)
        ->post(route('user.biodata.submit'), [
            'form_id' => $form->id,
            'is_submitted' => true,
            'responses' => [['form_field_id' => $field->id, 'value' => 'Baru']],
        ])
        ->assertRedirect(route('user.dashboard'));

    $fresh = FormSubmission::where('form_id', $form->id)->latest('id')->firstOrFail();

    expect($fresh->id)->not->toBe($rejected->id)
        ->and($fresh->status)->toBeInstanceOf(Submitted::class)
        ->and($rejected->fresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($rejected->formFieldResponses()->value('value'))->toBe('Lama');
});
