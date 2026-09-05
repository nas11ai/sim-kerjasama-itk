<?php

// tests/Feature/Submission/ResubmitTest.php
//
// BR-SM-04 mencakup submit DAN resubmit. BR-SM-03 menetapkan
// NEEDS_REVISION → RESUBMITTED → UNDER_REVIEW.

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
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

    // Antrean review yang menunggu revisi ini: reviewer sudah ditugaskan dan
    // evaluasinya belum selesai.
    $reviewer = Reviewer::factory()->create();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    ResubmitFormSubmission::run($submission, $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Resubmitted::class);

    // Yang memindahkan RESUBMITTED → UNDER_REVIEW adalah kode produksi —
    // FormSubmission::updateStatusBasedOnReviews(), yang dipanggil
    // ReviewController::assignReviewers() setelah reviewer ditugaskan. Test
    // ini tidak boleh melakukan transisinya sendiri: yang harus dibuktikan
    // adalah sistem yang mengantrekan ulang, bukan bahwa transisinya sah
    // (itu sudah dibuktikan SubmissionStateTest).
    $submission->fresh()->updateStatusBasedOnReviews();

    expect($submission->fresh()->status)
        ->toBeInstanceOf(UnderReview::class);
});

it('[SM-SUBMIT-12] resubmit dari DRAFT ditolak', function () {
    [$owner, $submission] = resubmitSliceSubmission(Draft::class);

    expect($owner->can('resubmit', $submission))->toBeFalse();
});
