<?php

// tests/Unit/FormSubmissionIsAccessibleByTest.php

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('submissions.view-all', 'web');
});

it('allows the lead researcher', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $user->id]);

    expect($submission->isAccessibleBy($user))->toBeTrue();
});

it('allows a user holding submissions.view-all', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');
    $submission = FormSubmission::factory()->create();

    expect($submission->isAccessibleBy($operator))->toBeTrue();
});

it('allows an active research member', function () {
    $user = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);
    $submission = FormSubmission::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'status' => 'active',
    ]);

    expect($submission->isAccessibleBy($user))->toBeTrue();
});

it('denies an inactive research member', function () {
    $user = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);
    $submission = FormSubmission::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'status' => 'inactive',
    ]);

    expect($submission->isAccessibleBy($user))->toBeFalse();
});

it('allows an assigned reviewer', function () {
    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $user->id]);
    $submission = FormSubmission::factory()->create();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    expect($submission->isAccessibleBy($user))->toBeTrue();
});

it('denies an unrelated user', function () {
    $submission = FormSubmission::factory()->create();

    expect($submission->isAccessibleBy(User::factory()->create()))->toBeFalse();
});
