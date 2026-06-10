<?php

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\SubmissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates submission status to pending', function () {
    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $user->id]);

    $result = SubmitFormSubmission::run($submission, $user);

    expect($result->status)->toBe(SubmissionStatus::PENDING)
        ->and($result->is_submitted)->toBeTrue();
});
