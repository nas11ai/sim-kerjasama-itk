<?php

// tests/Feature/Submission/SubmitUniquenessTest.php
//
// BR-SM-01 — satu submission AKTIF per (peneliti, period, scheme).
// Gerbang di Action mencerminkan predikat index
// one_active_submission_per_period_scheme.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: SubmissionPeriod, 2: Scheme, 3: Scheme} */
function uniquenessSliceContext(): array
{
    return [
        User::factory()->create(),
        SubmissionPeriod::factory()->create(),
        Scheme::factory()->create(),
        Scheme::factory()->create(),
    ];
}

function uniquenessSliceSubmission(
    User $owner,
    SubmissionPeriod $period,
    ?Scheme $scheme,
    string $state,
    bool $isSubmitted
): FormSubmission {
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme?->id,
        'status' => $state,
        'is_submitted' => $isSubmitted,
    ]);
}

it('[SM-SUBMIT-01] submit pertama ke sebuah skema berhasil', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-02] submit kedua ke skema yang sama ditolak', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    expect(fn () => SubmitFormSubmission::run($draft, $owner))
        ->toThrow(ValidationException::class);
    expect($draft->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-03] dua draft ke skema berbeda boleh hidup bersamaan', function () {
    [$owner, $period, $internal, $eksternal] = uniquenessSliceContext();
    $a = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);
    $b = uniquenessSliceSubmission($owner, $period, $eksternal, Draft::class, false);

    expect(FormSubmission::whereKey([$a->id, $b->id])->count())->toBe(2);
});

it('[SM-SUBMIT-04] submit ke skema lain tetap boleh', function () {
    [$owner, $period, $internal, $eksternal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $eksternal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-05] submit ulang ke skema yang sama boleh setelah ditolak', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Rejected::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-06] pengajuan tanpa skema tidak saling menghalangi', function () {
    [$owner, $period] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, null, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, null, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});
