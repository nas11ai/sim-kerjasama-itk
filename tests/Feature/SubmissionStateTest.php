<?php

use App\Models\FormSubmission;
use App\States\Approved;
use App\States\Draft;
use App\States\Submitted;
use App\States\Withdrawn;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

it('allows draft to submitted transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Draft::class,
    ]);

    $submission->status->transitionTo(Submitted::class);

    expect($submission->status)->toBeInstanceOf(Submitted::class);
});

it('prevents draft to approved transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Draft::class,
    ]);

    $submission->status->transitionTo(Approved::class);
})->throws(CouldNotPerformTransition::class);

it('allows approved to withdrawn transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Approved::class,
    ]);

    $submission->status->transitionTo(Withdrawn::class);

    expect($submission->status)->toBeInstanceOf(Withdrawn::class);
});

it('prevents withdrawn to approved transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Withdrawn::class,
    ]);

    $submission->status->transitionTo(Approved::class);
})->throws(CouldNotPerformTransition::class);
