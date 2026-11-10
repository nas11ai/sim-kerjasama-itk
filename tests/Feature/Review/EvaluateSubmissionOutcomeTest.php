<?php

// tests/Feature/Review/EvaluateSubmissionOutcomeTest.php
//
// BR-REV-05 — semua evaluasi selesai DAN tidak ada ReviewSummary `open`
//             → APPROVED.
// BR-REV-06 — semua evaluasi selesai DAN ada summary `open`
//             → NEEDS_REVISION.
// BR-REV-07 + BR-REV-14 — REJECTED tidak pernah otomatis (ADR 0006).
// ADR 0017 — persetujuan hampa hanya sah kalau `needs_review = false`.
//
// `not_required` dihitung selesai (register #14): dibaca harfiah sebagai
// "completed" saja, reviewer yang tidak diwajibkan menilai akan mengunci
// submission selamanya.
//
// Fixture `outcomeSubmission`, `reviewerOn`, `openSummaryOn` ada di tests/Support/fixtures.php.

use App\Actions\Review\EvaluateSubmissionOutcome;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;

it('[REV-OUTCOME-01] semua selesai tanpa summary terbuka menjadi APPROVED', function () {
    $submission = outcomeSubmission();
    reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

// Pemicunya kini juga evaluasi yang masuk (ADR 0006), yang bisa terjadi di status mana pun
// tempat reviewer masih bertugas. Hanya status yang menunggu keputusan yang boleh diputuskan.
it('status yang tidak menunggu keputusan review dibiarkan', function (string $state) {
    $submission = outcomeSubmission($state);
    reviewerOn($submission, 'completed');

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf($state);
})->with([
    'Draft' => Draft::class,
    'NeedsRevision' => NeedsRevision::class,
    'Approved' => Approved::class,
    'Rejected' => Rejected::class,
    'Withdrawn' => Withdrawn::class,
]);

it('[REV-OUTCOME-02] satu evaluasi belum selesai menahan transisi', function () {
    $submission = outcomeSubmission();
    reviewerOn($submission, 'completed');
    reviewerOn($submission, 'pending');

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-OUTCOME-03] summary terbuka menahan APPROVED', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a);

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->not->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-04] semua selesai dengan summary terbuka menjadi NEEDS_REVISION', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a);

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class);
});

it('[REV-OUTCOME-05] summary terakhir yang resolved membuka APPROVED', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a, 'resolved');

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-06] reviewer not_required ikut dihitung selesai', function () {
    $submission = outcomeSubmission();
    reviewerOn($submission, 'completed');
    reviewerOn($submission, 'not_required');

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-08] tidak ada kombinasi evaluasi yang menghasilkan REJECTED', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    openSummaryOn($submission, $a);

    EvaluateSubmissionOutcome::run($submission);

    // Skor serendah apa pun tidak boleh menolak. Penolakan hanya lewat aksi
    // Operator — Action ini sengaja tidak punya jalur ke sana.
    expect($submission->fresh()->status)->not->toBeInstanceOf(Rejected::class);
});

it('[REV-OUTCOME-09] fase tanpa review disetujui secara hampa', function () {
    $submission = outcomeSubmission(Submitted::class, needsReview: false);

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-10] fase yang butuh review tidak disetujui tanpa reviewer', function () {
    $submission = outcomeSubmission(Submitted::class, needsReview: true);

    EvaluateSubmissionOutcome::run($submission);

    // Kelalaian menugaskan reviewer tidak boleh berubah jadi persetujuan senyap.
    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});
