<?php

use App\States\Submission\SubmissionStatus;
use Spatie\ModelStates\State;

it('verifies SubmissionStatus extends spatie State base class and has helper methods', function () {
    expect(is_subclass_of(SubmissionStatus::class, State::class))->toBeTrue();
    expect(method_exists(SubmissionStatus::class, 'key'))->toBeTrue();
    expect(method_exists(SubmissionStatus::class, 'options'))->toBeTrue();
});
