<?php

// tests/Feature/Review/EvaluationStatusRecalculationTest.php
//
// BR-REV-05 — status evaluasi adalah cerminan keadaan sekarang, bukan
// stempel sekali jalan. ReviewFormResponse::updateReviewerEvaluationStatus()
// dulu hanya bisa menulis `completed` dan tak pernah kembali ke `pending`.

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\RecalculateEvaluationStatus;
use App\Actions\Review\SubmitEvaluationForm;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;

/** @return array{0: SubmissionReviewer, 1: ReviewEvaluationForm} */
function recalcFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = evaluationFormOn($detail, [], title: 'Substansi');

    return [$reviewer, $form];
}

function recalcSecondForm(ReviewEvaluationForm $first): ReviewEvaluationForm
{
    return evaluationFormOn($first->stepEvaluationForms()->sole()->formPhaseDetail, [], title: 'Anggaran');
}

it('mengembalikan status ke pending saat form wajib baru ditugaskan', function () {
    [$reviewer, $form] = recalcFixture();

    $assignment = AssignEvaluationForm::run($reviewer, $form->id, true);
    ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => 'submitted',
    ]);
    RecalculateEvaluationStatus::run($reviewer);

    expect($reviewer->fresh()->evaluation_status)->toBe('completed');

    // Form wajib kedua: pekerjaannya bertambah, jadi statusnya harus turun.
    AssignEvaluationForm::run($reviewer, recalcSecondForm($form)->id, true);

    expect($reviewer->fresh()->evaluation_status)->toBe('pending');
});

it('menyelaraskan status yang sudah melenceng saat submit, bukan membiarkannya', function () {
    [$reviewer, $form] = recalcFixture();

    // Form wajib yang belum dikerjakan: apa pun yang disubmit, reviewer ini
    // belum selesai.
    AssignEvaluationForm::run($reviewer, $form->id, true);

    // Yang disubmit justru form tidak wajib.
    $optional = AssignEvaluationForm::run($reviewer, recalcSecondForm($form)->id, false);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $optional->id, 'review_evaluation_form_version_id' => $optional->reviewEvaluationForm->latestVersion->id,
        'status' => 'draft',
    ]);

    // Status melenceng jadi `completed` padahal form wajibnya masih menganggur.
    $reviewer->update(['evaluation_status' => 'completed']);

    SubmitEvaluationForm::run($response);

    // Versi lama hanya bisa menulis `completed`, jadi ia mendiamkan stempel
    // yang salah. Hitung ulang yang benar menurunkannya kembali.
    expect($reviewer->fresh()->evaluation_status)->toBe('pending');
});

it('menjatuhkan status ke not_required saat submit tidak menyisakan penugasan aktif', function () {
    [$reviewer, $form] = recalcFixture();

    $assignment = AssignEvaluationForm::run($reviewer, $form->id, true);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => 'draft',
    ]);

    // Penugasannya dicabut dari peredaran sementara jawabannya masih draft.
    // Versi lama hanya melihat penugasan `required()->active()` — tidak ada,
    // jadi ia menstempel `completed` untuk pekerjaan yang tidak ada.
    $assignment->update(['is_active' => false]);

    SubmitEvaluationForm::run($response);

    expect($reviewer->fresh()->evaluation_status)->toBe('not_required');
});

it('mencatat jejak audit saat status evaluasi benar-benar berpindah', function () {
    [$reviewer, $form] = recalcFixture();

    // Berangkat dari `not_required`; form wajib yang masuk memindahkannya ke
    // `pending`, dan perpindahan itulah yang dicatat.
    $reviewer->update(['evaluation_status' => 'not_required']);

    AssignEvaluationForm::run($reviewer, $form->id, true);

    $entri = Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $reviewer->getMorphClass())
        ->where('subject_id', $reviewer->id)
        ->get();

    expect($entri)->toHaveCount(1)
        ->and($entri->first()?->description)->toBe('Status evaluasi diperbarui');
});

it('tidak mencatat apa pun saat status evaluasi tidak berubah', function () {
    [$reviewer] = recalcFixture();

    // Tanpa penugasan aktif jawabannya `not_required`. Disetel ke nilai itu
    // lebih dulu, hitung ulangnya tidak memindahkan apa pun.
    $reviewer->update(['evaluation_status' => 'not_required']);
    RecalculateEvaluationStatus::run($reviewer);

    expect(Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $reviewer->getMorphClass())
        ->where('subject_id', $reviewer->id)
        ->count())->toBe(0);
});
