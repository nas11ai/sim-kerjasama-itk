<?php

namespace App\Data\Submission;

use App\Models\FormSubmission;
use App\Models\User;
use Spatie\LaravelData\Data;

class SubmissionAbilitiesData extends Data
{
    public function __construct(
        public bool $view,
        public bool $update,
        public bool $submit,
        public bool $withdraw,
    ) {}

    public static function forUser(User $user, FormSubmission $submission): self
    {
        return new self(
            view: $user->can('view', $submission),
            update: $user->can('update', $submission),
            submit: $user->can('submit', $submission),
            withdraw: $user->can('withdraw', $submission),
        );
    }
}
