<?php

use App\States\Submission\SubmissionStatus;
use Spatie\ModelStates\State;

it('verifies SubmissionStatus extends spatie State base class', function () {
    expect(is_subclass_of(SubmissionStatus::class, State::class))->toBeTrue();
});
