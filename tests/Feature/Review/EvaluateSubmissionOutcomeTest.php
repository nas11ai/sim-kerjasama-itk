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

use App\Actions\Review\EvaluateSubmissionOutcome;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;

function outcomeSubmission(string $state = UnderReview::class, bool $needsReview = true): FormSubmission
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => $needsReview]);

    return FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'status' => $state,
        'form_phase_detail_id' => $detail->id,
    ]);
}

function reviewerOn(FormSubmission $submission, string $evaluationStatus): SubmissionReviewer
{
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
    ]);
}

function openSummaryOn(FormSubmission $submission, SubmissionReviewer $assignment, string $status = 'open'): void
{
    ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $assignment->reviewer_id,
        'status' => $status,
        'summary_notes' => 'Perkuat metodologi.',
    ]);
}

it('[REV-OUTCOME-01] semua selesai tanpa summary terbuka menjadi APPROVED', function () {
    $submission = outcomeSubmission();
    reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');

    EvaluateSubmissionOutcome::run($submission);

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

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
