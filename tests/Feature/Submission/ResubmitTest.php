<?php

// tests/Feature/Submission/ResubmitTest.php
//
// BR-SM-04 mencakup submit DAN resubmit. BR-SM-03 menetapkan
// NEEDS_REVISION → RESUBMITTED → UNDER_REVIEW.

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\UnderReview;
use Illuminate\Auth\Access\AuthorizationException;

/** @return array{0: User, 1: FormSubmission} */
function resubmitSliceSubmission(string $state = NeedsRevision::class): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

it('[SM-SUBMIT-12] ketua pengaju resubmit dari NEEDS_REVISION', function () {
    [$owner, $submission] = resubmitSliceSubmission();

    expect($owner->can('resubmit', $submission))->toBeTrue();

    ResubmitFormSubmission::run($submission, $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Resubmitted::class);
});

it('[SM-SUBMIT-12] bukan pemilik tidak bisa resubmit', function () {
    [, $submission] = resubmitSliceSubmission();
    $orang = User::factory()->create();

    expect($orang->can('resubmit', $submission))->toBeFalse();
    expect(fn () => ResubmitFormSubmission::run($submission, $orang))
        ->toThrow(AuthorizationException::class);
});

it('[SM-STATE-03] revisi kembali masuk antrean review', function () {
    [$owner, $submission] = resubmitSliceSubmission();

    ResubmitFormSubmission::run($submission, $owner);

    // RESUBMITTED → UNDER_REVIEW adalah transisi yang sah (SubmissionStateTest
    // membuktikannya); skenario menuntut keduanya terjadi berurutan.
    $submission->fresh()->status->transitionTo(UnderReview::class);

    expect($submission->fresh()->status)
        ->toBeInstanceOf(UnderReview::class);
});

it('[SM-SUBMIT-12] resubmit dari DRAFT ditolak', function () {
    [$owner, $submission] = resubmitSliceSubmission(Draft::class);

    expect($owner->can('resubmit', $submission))->toBeFalse();
});
