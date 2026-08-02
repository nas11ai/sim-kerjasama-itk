<?php

use App\Models\FormSubmission;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

it('allows draft to submitted transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Draft::class,
    ]);

    $submission->status->transitionTo(Submitted::class);

    expect($submission->status)->toBeInstanceOf(Submitted::class);
});

it('allows submitted to under review transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Submitted::class,
    ]);

    $submission->status->transitionTo(UnderReview::class);

    expect($submission->status)->toBeInstanceOf(UnderReview::class);
});

it('allows under review to approved transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => UnderReview::class,
    ]);

    $submission->status->transitionTo(Approved::class);

    expect($submission->status)->toBeInstanceOf(Approved::class);
});

it('allows under review to needs revision transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => UnderReview::class,
    ]);

    $submission->status->transitionTo(NeedsRevision::class);

    expect($submission->status)->toBeInstanceOf(NeedsRevision::class);
});

it('allows under review to rejected transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => UnderReview::class,
    ]);

    $submission->status->transitionTo(Rejected::class);

    expect($submission->status)->toBeInstanceOf(Rejected::class);
});

it('allows needs revision to resubmitted transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => NeedsRevision::class,
    ]);

    $submission->status->transitionTo(Resubmitted::class);

    expect($submission->status)->toBeInstanceOf(Resubmitted::class);
});

it('allows resubmitted to under review transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Resubmitted::class,
    ]);

    $submission->status->transitionTo(UnderReview::class);

    expect($submission->status)->toBeInstanceOf(UnderReview::class);
});

it('allows approved to withdrawn transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Approved::class,
    ]);

    $submission->status->transitionTo(Withdrawn::class);

    expect($submission->status)->toBeInstanceOf(Withdrawn::class);
});

it('prevents draft to approved transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Draft::class,
    ]);

    $submission->status->transitionTo(Approved::class);
})->throws(CouldNotPerformTransition::class);

it('prevents approved to draft transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Approved::class,
    ]);

    $submission->status->transitionTo(Draft::class);
})->throws(CouldNotPerformTransition::class);

it('prevents rejected to approved transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Rejected::class,
    ]);

    $submission->status->transitionTo(Approved::class);
})->throws(CouldNotPerformTransition::class);

it('prevents withdrawn to approved transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Withdrawn::class,
    ]);

    $submission->status->transitionTo(Approved::class);
})->throws(CouldNotPerformTransition::class);

it('prevents draft to resubmitted transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Draft::class,
    ]);

    $submission->status->transitionTo(Resubmitted::class);
})->throws(CouldNotPerformTransition::class);

it('prevents rejected to resubmitted transition', function () {
    $submission = FormSubmission::factory()->create([
        'status' => Rejected::class,
    ]);

    $submission->status->transitionTo(Resubmitted::class);
})->throws(CouldNotPerformTransition::class);
