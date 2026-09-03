<?php

// tests/Feature/SubmissionUniquenessTest.php

use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use Illuminate\Database\UniqueConstraintViolationException;

function submissionFor(User $user, SubmissionPeriod $period, ?Scheme $scheme, bool $submitted, string $state): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => $user->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme?->id,
        'is_submitted' => $submitted,
        'status' => $state,
    ]);
}

it('blocks a second submitted submission for the same period and scheme', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    submissionFor($user, $period, $scheme, true, Submitted::class);
    submissionFor($user, $period, $scheme, true, Submitted::class);
})->throws(UniqueConstraintViolationException::class);

it('allows several drafts for the same period and scheme', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    submissionFor($user, $period, $scheme, false, Draft::class);
    submissionFor($user, $period, $scheme, false, Draft::class);

    expect(FormSubmission::count())->toBe(2);
});

it('allows resubmitting after a rejection', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    submissionFor($user, $period, $scheme, true, Rejected::class);
    submissionFor($user, $period, $scheme, true, Submitted::class);

    expect(FormSubmission::count())->toBe(2);
});

it('does not constrain submissions without a scheme', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();

    submissionFor($user, $period, null, true, Submitted::class);
    submissionFor($user, $period, null, true, Submitted::class);

    expect(FormSubmission::count())->toBe(2);
});
