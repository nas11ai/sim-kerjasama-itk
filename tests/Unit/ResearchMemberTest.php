<?php

// tests/Unit/ResearchMemberTest.php

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a submission and a user profile', function () {
    $member = ResearchMember::factory()->create();

    expect($member->formSubmission)->toBeInstanceOf(FormSubmission::class)
        ->and($member->userProfile)->toBeInstanceOf(UserProfile::class);
});

it('rejects a role outside the allowed set', function () {
    ResearchMember::factory()->create(['role' => 'principal']);
})->throws(QueryException::class);

it('prevents the same profile twice on one submission', function () {
    $member = ResearchMember::factory()->create();

    ResearchMember::factory()->create([
        'form_submission_id' => $member->form_submission_id,
        'user_profile_id' => $member->user_profile_id,
    ]);
})->throws(UniqueConstraintViolationException::class);
