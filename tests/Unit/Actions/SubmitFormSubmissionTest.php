<?php

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\States\Submission\Submitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('[SM-STATE-01] updates submission status to submitted', function () {
    $user = verifiedResearcher();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $user->id,
    ]);

    $result = SubmitFormSubmission::run($submission, $user);

    expect($result->status->equals(Submitted::class))->toBeTrue()
        ->and($result->is_submitted)->toBeTrue();
});
