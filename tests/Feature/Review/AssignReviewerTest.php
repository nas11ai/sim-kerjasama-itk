<?php

// tests/Feature/Review/AssignReviewerTest.php
//
// BR-REV-01 — submission berpindah ke UNDER_REVIEW saat jumlah minimum
// reviewer tercapai. Ambangnya dari scheme.rules.min_reviewer_count,
// BUKAN konstanta di kode.
//
// BR-REV-03 — reviewer yang sama tidak bisa ditugaskan dua kali pada
// siklus yang sama. BR-REV-09 — reviewer di luar masa tugasnya ditolak.

use App\Actions\Review\AssignReviewer;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Validation\ValidationException;

/** @return array{0: FormSubmission, 1: Scheme} */
function assignSliceSubmission(int $minReviewers = 2): array
{
    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => $minReviewers, 'max_reviewer_workload' => 10],
    ]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
        'status' => Submitted::class,
    ]);

    return [$submission, $scheme];
}

function activeReviewer(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

it('[REV-ASSIGN-01] reviewer pertama tidak memindahkan status', function () {
    [$submission] = assignSliceSubmission();

    AssignReviewer::run($submission, activeReviewer());

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[REV-ASSIGN-02] belum mencapai minimum, status bertahan', function () {
    [$submission] = assignSliceSubmission(minReviewers: 3);

    AssignReviewer::run($submission, activeReviewer());
    AssignReviewer::run($submission, activeReviewer());

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class)
        ->and($submission->submissionReviewers()->count())->toBe(2);
});

it('[REV-ASSIGN-03] reviewer terakhir memindahkan ke UNDER_REVIEW', function () {
    [$submission] = assignSliceSubmission();

    AssignReviewer::run($submission, activeReviewer());
    AssignReviewer::run($submission, activeReviewer());

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-ASSIGN-03] ambang diambil dari skema, bukan konstanta', function () {
    // Skema yang menuntut satu reviewer saja harus berpindah setelah satu.
    // Kalau ambangnya di-hardcode 2, test ini memerah.
    [$submission] = assignSliceSubmission(minReviewers: 1);

    AssignReviewer::run($submission, activeReviewer());

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-ASSIGN-05] reviewer yang sama ditolak pada siklus yang sama', function () {
    [$submission] = assignSliceSubmission(minReviewers: 3);
    $reviewer = activeReviewer();

    AssignReviewer::run($submission, $reviewer);
    AssignReviewer::run($submission, $reviewer);
})->throws(ValidationException::class);

it('[REV-ASSIGN-08] reviewer di luar masa tugas ditolak', function () {
    [$submission] = assignSliceSubmission();

    $expired = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subYear(),
        'end_date' => now()->subDay(),
    ]);

    AssignReviewer::run($submission, $expired);
})->throws(ValidationException::class);

it('[REV-ASSIGN-04] konflik kepentingan tetap ditolak lewat Action', function () {
    [$submission] = assignSliceSubmission();

    // Guard BR-REV-02 dipanggil dari sini — bukan diulang implementasinya.
    $owner = Reviewer::factory()->create(['user_id' => $submission->submitted_by]);

    AssignReviewer::run($submission, $owner);
})->throws(ValidationException::class);

it('[REV-ASSIGN-01] penugasan lahir dengan evaluation_status pending', function () {
    [$submission] = assignSliceSubmission();

    $assignment = AssignReviewer::run($submission, activeReviewer());

    expect($assignment->evaluation_status)->toBe('pending')
        ->and($assignment->status)->toBe('active');
});

it('[REV-ASSIGN-01] penugasan selama NEEDS_REVISION tidak memindahkan status', function () {
    [$submission] = assignSliceSubmission(minReviewers: 1);
    $submission->update(['status' => NeedsRevision::class]);

    // BR-REV-01 memindahkan SUBMITTED → UNDER_REVIEW saja; revisi kembali ke antrean lewat kirim ulang (BR-SM-03).
    AssignReviewer::run($submission, activeReviewer());

    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class)
        ->and($submission->activeSubmissionReviewers()->count())->toBe(1);
});
