<?php

// tests/Feature/Review/EvaluationFormAssignmentTest.php
//
// Pencabutan penugasan tidak punya Action tersendiri: SubmissionReviewer::
// removeFormAssignment() nol pemanggil dan sudah dicabut, sementara jalur
// nyatanya ada di ReviewerFormAssignmentController yang lebih lengkap.

use App\Actions\Review\AssignEvaluationForm;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;

/** @return array{0: SubmissionReviewer, 1: ReviewEvaluationForm} */
function assignmentFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'not_required',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return [$reviewer, $form];
}

it('menugaskan form dan menurunkan status evaluasi jadi pending', function () {
    [$reviewer, $form] = assignmentFixture();

    $assignment = AssignEvaluationForm::run($reviewer, $form->id);

    expect($assignment->review_evaluation_form_id)->toBe($form->id)
        ->and($assignment->is_required)->toBeTrue()
        ->and($reviewer->fresh()->evaluation_status)->toBe('pending');
});

it('menugaskan form tidak wajib tanpa memaksa is_required', function () {
    [$reviewer, $form] = assignmentFixture();

    $assignment = AssignEvaluationForm::run($reviewer, $form->id, false);

    // Tidak ada form wajib yang menunggu, jadi evaluasinya sudah beres.
    expect($assignment->is_required)->toBeFalse()
        ->and($reviewer->fresh()->evaluation_status)->toBe('completed');
});

it('mencatat satu entri audit untuk satu penugasan — BR-RPT-09', function () {
    [$reviewer, $form] = assignmentFixture();

    $assignment = AssignEvaluationForm::run($reviewer, $form->id);

    $entries = Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $assignment->getMorphClass())
        ->where('subject_id', $assignment->id)
        ->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()?->description)->toBe('Form evaluasi ditugaskan');
});

it('menugaskan ulang form yang sama tanpa menabrak indeks unik', function () {
    // Tabelnya punya unique (submission_reviewer_id, review_evaluation_form_id).
    // `create()` melemparkan QueryException dan membatalkan seluruh batch.
    [$reviewer, $form] = assignmentFixture();

    $pertama = AssignEvaluationForm::run($reviewer, $form->id, true);
    $kedua = AssignEvaluationForm::run($reviewer, $form->id, false);

    expect($kedua->id)->toBe($pertama->id)
        ->and($kedua->is_required)->toBeFalse()
        ->and(ReviewerFormAssignment::count())->toBe(1);
});
