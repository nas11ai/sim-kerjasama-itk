<?php

use App\Models\FormSubmission;
use App\States\Submission\SubmissionStatus;
use Spatie\ModelStates\State;

it('verifies SubmissionStatus extends spatie State base class and has helper methods', function () {
    expect(is_subclass_of(SubmissionStatus::class, State::class))->toBeTrue();
    expect(method_exists(SubmissionStatus::class, 'key'))->toBeTrue();
    expect(method_exists(SubmissionStatus::class, 'options'))->toBeTrue();
});

it('[K2] label pilihan status sama dengan label tiap state', function () {
    foreach (SubmissionStatus::options() as $state => $label) {
        expect((new $state(new FormSubmission))->label())->toBe($label);
    }
});
