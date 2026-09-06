<?php

// tests/Feature/Review/ReviewSummaryGateTest.php
//
// BR-FE-06 — ReviewSummary baru boleh dibuat setelah `evaluation_status`
// bernilai `completed` atau `not_required`.
//
// Gerbangnya ada di **status penugasan**, bukan pada ada-tidaknya jawaban
// evaluasi yang tersubmit. Keduanya sering tertukar: `not_required` tidak
// pernah punya jawaban tersubmit, tapi tetap berhak membuka ringkasan —
// kalau tidak, reviewer yang memang tidak diwajibkan menilai tidak akan
// pernah bisa menutup ulir yang ia buka.

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Support\ReviewSummaryGuard;
use Illuminate\Validation\ValidationException;

function assignmentWithEvaluation(string $evaluationStatus): SubmissionReviewer
{
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
    ]);

    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
    ]);
}

it('[REV-EVAL-04] ringkasan ditolak selama evaluasi belum selesai', function () {
    $assignment = assignmentWithEvaluation('pending');

    expect($assignment->hasSettledEvaluation())->toBeFalse();

    ReviewSummaryGuard::assertMayCreate($assignment);
})->throws(ValidationException::class);

it('[REV-EVAL-05] ringkasan terbuka setelah evaluasi selesai', function () {
    $assignment = assignmentWithEvaluation('completed');

    expect($assignment->hasSettledEvaluation())->toBeTrue();

    ReviewSummaryGuard::assertMayCreate($assignment);

    expect(true)->toBeTrue();
});

it('[REV-EVAL-06] status not_required juga membuka ringkasan', function () {
    // Tidak punya jawaban evaluasi tersubmit sama sekali — dan memang tidak
    // perlu. Gerbangnya status penugasan, bukan keberadaan jawaban.
    $assignment = assignmentWithEvaluation('not_required');

    expect($assignment->hasSettledEvaluation())->toBeTrue();

    ReviewSummaryGuard::assertMayCreate($assignment);

    expect(true)->toBeTrue();
});
