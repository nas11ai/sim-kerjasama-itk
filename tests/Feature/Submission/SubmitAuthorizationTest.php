<?php

// tests/Feature/Submission/SubmitAuthorizationTest.php
//
// BR-SM-04 — hanya submitted_by yang boleh submit. Ditegakkan
// SubmitFormSubmission dan dikunci ADR 0015 (Gate::before tidak melewatinya).

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

/** @return array{0: User, 1: FormSubmission} */
function submitSliceDraft(string $state = Draft::class): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

function submitSliceEnrol(FormSubmission $submission, User $user, string $role): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => 'active',
    ]);
}

function submitSliceSuperAdmin(): User
{
    Role::findOrCreate('Super Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    return $admin;
}

it('[SM-SUBMIT-09] ketua pengaju submit pengajuannya sendiri', function () {
    [$owner, $submission] = submitSliceDraft();

    SubmitFormSubmission::run($submission, $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-10] co-investigator tidak bisa submit', function () {
    [, $submission] = submitSliceDraft();
    $co = User::factory()->create();
    submitSliceEnrol($submission, $co, ResearchMember::ROLE_CO_INVESTIGATOR);

    expect(fn () => SubmitFormSubmission::run($submission, $co))
        ->toThrow(AuthorizationException::class);
    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-11] anggota biasa tidak bisa submit', function () {
    [, $submission] = submitSliceDraft();
    $member = User::factory()->create();
    submitSliceEnrol($submission, $member, ResearchMember::ROLE_MEMBER);

    expect(fn () => SubmitFormSubmission::run($submission, $member))
        ->toThrow(AuthorizationException::class);
    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-13] super admin bukan pemilik tidak bisa submit', function () {
    [, $submission] = submitSliceDraft();
    $admin = submitSliceSuperAdmin();

    expect($admin->can('submit', $submission))->toBeFalse();
    expect(fn () => SubmitFormSubmission::run($submission, $admin))
        ->toThrow(AuthorizationException::class);
    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-14] super admin tetap bisa melihat, menyunting, dan menarik', function () {
    [, $submission] = submitSliceDraft(Approved::class);
    $admin = submitSliceSuperAdmin();

    $this->actingAs($admin)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();

    expect($admin->can('update', $submission))->toBeTrue()
        ->and($admin->can('withdraw', $submission))->toBeTrue();
});
