<?php

// tests/Unit/SubmissionAbilitiesDataTest.php

use App\Data\Submission\SubmissionAbilitiesData;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => Permission::findOrCreate('submissions.view-all', 'web'));

it('mirrors the policy for the lead researcher', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'status' => Draft::class,
    ]);

    $abilities = SubmissionAbilitiesData::forUser($user, $submission);

    expect($abilities->view)->toBeTrue()
        ->and($abilities->update)->toBeTrue()
        ->and($abilities->submit)->toBeTrue()
        ->and($abilities->withdraw)->toBeFalse();
});

it('denies everything to an unrelated user', function () {
    $abilities = SubmissionAbilitiesData::forUser(
        User::factory()->create(),
        FormSubmission::factory()->create(['status' => Draft::class]),
    );

    expect($abilities->view)->toBeFalse()
        ->and($abilities->update)->toBeFalse()
        ->and($abilities->withdraw)->toBeFalse();
});

it('serialises to the shape the frontend expects', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'status' => Draft::class,
    ]);

    expect(SubmissionAbilitiesData::forUser($user, $submission)->toArray())
        ->toHaveKeys(['view', 'update', 'submit', 'withdraw']);
});

it('mirrors the policy withdraw ability for an authorised operator', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    // FormTypeFactory hard-codes 'name' unik, jadi Form dibuat manual dan
    // dipakai bersama supaya dua FormSubmission di test ini tidak bentrok
    // lewat cascade default (lihat FormSubmissionPolicyTest).
    $formType = FormType::factory()->create(['name' => 'Tipe Form Uji Withdraw Abilities']);
    $form = Form::factory()->create(['form_type_id' => $formType->id]);

    $approved = FormSubmission::factory()->create(['form_id' => $form->id, 'status' => Approved::class]);
    $draft = FormSubmission::factory()->create(['form_id' => $form->id, 'status' => Draft::class]);

    expect(SubmissionAbilitiesData::forUser($operator, $approved)->withdraw)->toBeTrue()
        ->and(SubmissionAbilitiesData::forUser($operator, $draft)->withdraw)->toBeFalse();
});
