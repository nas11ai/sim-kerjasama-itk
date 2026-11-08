<?php

// tests/Feature/Review/ReviewOutcomeWiringTest.php
//
// EvaluateSubmissionOutcome dipanggil dari jalur nyata, bukan hanya ada.
//
// Pemicunya dua: saat ringkasan ditutup (BR-REV-06 → APPROVED), dan saat
// evaluasi terakhir masuk. Tanpa penyambungan ini seluruh BR-REV-05/06
// benar di test tapi tidak pernah berjalan di aplikasi.
//
// Jalur status ulir selain resolve sudah dicabut: lihat
// ReviewSummaryStatusEndpointRemovedTest.

use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormField;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;

/** @return array{0: User, 1: FormSubmission, 2: ReviewSummary} */
function outcomeWiringFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);

    $reviewerUser = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $reviewerUser->id]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);

    return [$reviewerUser, $submission, $summary];
}

it('[REV-OUTCOME-05] menutup ringkasan terakhir menyetujui pengajuan lewat jalur HTTP', function () {
    [$reviewerUser, $submission, $summary] = outcomeWiringFixture();

    $this->actingAs($reviewerUser)
        ->post(route('review-summaries.resolve', $summary))
        ->assertRedirect();

    // Seluruh evaluasi sudah selesai dan tidak ada ringkasan terbuka lagi —
    // BR-REV-05 terpenuhi, dan transisinya harus terjadi tanpa aksi tambahan.
    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-03] ringkasan yang masih terbuka menahan persetujuan', function () {
    [$reviewerUser, $submission, $summary] = outcomeWiringFixture();

    ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $summary->reviewer_id,
        'status' => 'open',
        'summary_notes' => 'Catatan kedua.',
    ]);

    $this->actingAs($reviewerUser)
        ->post(route('review-summaries.resolve', $summary))
        ->assertRedirect();

    expect($submission->fresh()->status)->not->toBeInstanceOf(Approved::class);
});

function outcomeSubmitEvaluation(User $reviewer, ReviewerFormAssignment $assignment): void
{
    $field = ReviewFormField::create([
        'review_evaluation_form_id' => $assignment->review_evaluation_form_id,
        'label' => 'Kelayakan',
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'is_required' => false,
        'order' => 1,
    ]);

    test()->actingAs($reviewer)
        ->post(route('reviewer.evaluation-form.submit', $assignment), ['responses' => [$field->id => 'Layak']])
        ->assertSessionHasNoErrors();
}

/** Kursi reviewer kedua pada pengajuan yang sama, dengan evaluasi berstatus $evaluation. */
function outcomeSecondSeat(FormSubmission $submission, string $evaluation): Reviewer
{
    $reviewer = Reviewer::factory()->create();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);

    return $reviewer;
}

// ADR 0006 — EvaluateSubmissionOutcome dipanggil setiap kali evaluasi berubah, bukan hanya saat
// ulir ditutup. Tanpa pemicu ini pengajuan yang tidak pernah dibuka ulirnya menggantung di
// UNDER_REVIEW selamanya, padahal BR-REV-05 terpenuhi.
it('[REV-OUTCOME-01] evaluasi terakhir tanpa ulir terbuka menyetujui pengajuan lewat jalur HTTP', function () {
    $fx = reviewedDetailFixture('+1 month', '+2 months');

    outcomeSubmitEvaluation($fx['reviewer'], $fx['assignment']);

    expect($fx['submission']->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-02] evaluasi pertama dari dua reviewer belum memutuskan', function () {
    $fx = reviewedDetailFixture('+1 month', '+2 months');
    outcomeSecondSeat($fx['submission'], 'pending');

    outcomeSubmitEvaluation($fx['reviewer'], $fx['assignment']);

    expect($fx['submission']->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-OUTCOME-04] evaluasi terakhir saat ulir rekan masih terbuka meminta revisi', function () {
    $fx = reviewedDetailFixture('+1 month', '+2 months');
    $peer = outcomeSecondSeat($fx['submission'], 'completed');
    ReviewSummary::create([
        'form_submission_id' => $fx['submission']->id,
        'reviewer_id' => $peer->id,
        'status' => 'open',
        'summary_notes' => 'Perjelas jadwal.',
    ]);

    outcomeSubmitEvaluation($fx['reviewer'], $fx['assignment']);

    expect($fx['submission']->fresh()->status)->toBeInstanceOf(NeedsRevision::class);
});
