<?php

// tests/Feature/FormSubmissionPolicyTest.php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => Permission::findOrCreate('submissions.view-all', 'web'));

it('lets the lead researcher update a draft', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'status' => Draft::class,
    ]);

    expect($user->can('update', $submission))->toBeTrue();
});

it('stops an operator from updating someone elses submission', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    expect($operator->can('view', $submission))->toBeTrue()
        ->and($operator->can('update', $submission))->toBeFalse();
});

it('only lets an operator withdraw an approved submission', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    // FormTypeFactory hard-codes 'name' unik, jadi Form dibuat manual agar
    // dua FormSubmission di test ini tidak bentrok lewat cascade default.
    $formType = FormType::factory()->create(['name' => 'Tipe Form Uji Withdraw']);
    $form = Form::factory()->create(['form_type_id' => $formType->id]);

    $approved = FormSubmission::factory()->create(['form_id' => $form->id, 'status' => Approved::class]);
    $draft = FormSubmission::factory()->create(['form_id' => $form->id, 'status' => Draft::class]);

    expect($operator->can('withdraw', $approved))->toBeTrue()
        ->and($operator->can('withdraw', $draft))->toBeFalse();
});
