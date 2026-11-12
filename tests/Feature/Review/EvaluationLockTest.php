<?php

// tests/Feature/Review/EvaluationLockTest.php
//
// BR-FE-05, dicerminkan BR-REV-08 — `ReviewFormResponse` terkunci setelah
// `status = submitted`. Satu-satunya pasangan yang teksnya identik kata per
// kata di dua dokumen, jadi skenarionya membawa dua tag.
//
// Aturannya ada di Form Engine karena `ReviewFormResponse` entitasnya;
// BC Review menyebutnya ulang supaya bisa dibaca utuh, bukan supaya
// diimplementasikan dua kali.

use App\Actions\Review\SubmitEvaluationForm;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;

/** @return array{0: ReviewFormResponse, 1: SubmissionReviewer} */
function lockFixture(string $status = 'draft', bool $required = true): array
{
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'status' => UnderReview::class,
    ]);

    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    // ReviewEvaluationForm menempel pada FormPhaseDetail (BR-FE-06):
    // form evaluasi selalu milik satu tahap, bukan mengambang.
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $form = evaluationFormOn($detail, [], title: 'Form Substansi '.fake()->unique()->numerify('####'));

    $formAssignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $assignment->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => $required,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $formAssignment->id, 'review_evaluation_form_version_id' => $formAssignment->reviewEvaluationForm->latestVersion->id,
        'status' => $status,
    ]);

    return [$response, $assignment];
}

it('[REV-EVAL-01] evaluasi draft masih bisa disunting', function () {
    [$response] = lockFixture('draft');

    expect($response->canBeEdited())->toBeTrue();
});

it('[REV-EVAL-02] evaluasi terkunci setelah disubmit', function () {
    [$response] = lockFixture('submitted');

    // Terkunci berarti tidak bisa disunting — bukan sekadar ditandai.
    expect($response->canBeEdited())->toBeFalse()
        ->and($response->isSubmitted())->toBeTrue();
});

it('[REV-EVAL-02] evaluasi yang terkunci deadline juga tidak bisa disunting', function () {
    [$response] = lockFixture('locked');

    expect($response->canBeEdited())->toBeFalse()
        ->and($response->isLocked())->toBeTrue();
});

it('[REV-EVAL-03] submit evaluasi menyelesaikan penugasan', function () {
    [$response, $assignment] = lockFixture('draft');

    SubmitEvaluationForm::run($response);

    // Seluruh form wajib sudah tersubmit, jadi penugasannya selesai —
    // itulah syarat tombol Selesai menilai (BR-REV-16).
    expect($response->fresh()->status)->toBe('submitted')
        ->and($assignment->fresh()->evaluation_status)->toBe('completed')
        ->and($assignment->fresh()->hasSettledEvaluation())->toBeTrue();
});

it('[REV-EVAL-03] evaluasi yang sudah disubmit tidak bisa disubmit ulang', function () {
    [$response] = lockFixture('submitted');

    expect(SubmitEvaluationForm::run($response))->toBeFalse();
});
