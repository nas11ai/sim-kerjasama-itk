<?php

// tests/Feature/Review/EvaluationPendingTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\PendingEvaluationCheck;

function submissionWithReviewer(string $evaluationStatus): FormSubmission
{
    $submission = FormSubmission::factory()->create();

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
    ]);

    return $submission->fresh();
}

it('menyatakan ada evaluasi tertunda saat satu reviewer belum selesai', function () {
    expect(PendingEvaluationCheck::run(submissionWithReviewer('pending')))->toBeTrue();
});

it('menyatakan tidak ada evaluasi tertunda saat seluruhnya selesai', function () {
    expect(PendingEvaluationCheck::run(submissionWithReviewer('completed')))->toBeFalse();
});

it('menganggap pengajuan tanpa reviewer tidak punya evaluasi tertunda', function () {
    expect(PendingEvaluationCheck::run(FormSubmission::factory()->create()))->toBeFalse();
});

it('mengabaikan reviewer yang sudah diganti — BR-REV-05 menghitung yang aktif saja', function () {
    $submission = FormSubmission::factory()->create();

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    // Sejarah, bukan pekerjaan: evaluasinya tertinggal 'pending' karena
    // markAsReplaced() hanya menyentuh status.
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);

    expect(PendingEvaluationCheck::run($submission->fresh()))->toBeFalse();
});

it('sepakat dengan canProceed — BR-REV-05 satu definisi, dua pemakai', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'is_submitted' => true,
    ]);

    $active = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    // Sejarah: tidak boleh menahan keduanya.
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);

    expect(PendingEvaluationCheck::run($submission->fresh()))->toBeTrue()
        ->and($submission->fresh()->canProceed())->toBeFalse();

    $active->update(['evaluation_status' => 'not_required']);

    expect(PendingEvaluationCheck::run($submission->fresh()))->toBeFalse()
        ->and($submission->fresh()->canProceed())->toBeTrue();
});
