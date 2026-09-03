<?php

// tests/Feature/FormSubmissionPolicyTest.php

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
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

// I2 — matriks lengkap sebelumnya cuma menguji lead+Draft -> true dan
// operator -> false; menghapus state gate di update() tetap membuat suite
// lama hijau. Ini menutup celah itu: lead sendiri pun tidak boleh update
// begitu statusnya sudah lepas dari Draft/NeedsRevision.
it('stops the lead researcher from updating a submitted submission', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'status' => Submitted::class,
    ]);

    expect($user->can('update', $submission))->toBeFalse();
});

it('stops the lead researcher from updating an approved submission', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'status' => Approved::class,
    ]);

    expect($user->can('update', $submission))->toBeFalse();
});

// I2 — submit() belum pernah diuji menolak non-owner sama sekali.
it('stops a non-owner from submitting someone elses submission', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    expect($user->can('submit', $submission))->toBeFalse();
});

it('only lets an operator withdraw an approved submission', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    $approved = FormSubmission::factory()->create(['status' => Approved::class]);
    $draft = FormSubmission::factory()->create(['status' => Draft::class]);

    expect($operator->can('withdraw', $approved))->toBeTrue()
        ->and($operator->can('withdraw', $draft))->toBeFalse();
});

// Triage must-fix — BR-SM-11: withdrawal bukan self-service. Sebelumnya
// hanya operator+state yang divariasikan; lead researcher tidak pernah
// dicoba sama sekali walau dia submitted_by dari submission APPROVED-nya
// sendiri.
it('stops the lead researcher from withdrawing their own approved submission', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'status' => Approved::class,
    ]);

    expect($user->can('withdraw', $submission))->toBeFalse();
});
