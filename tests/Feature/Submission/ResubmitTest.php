<?php

// tests/Feature/Submission/ResubmitTest.php
//
// BR-SM-04 mencakup submit DAN resubmit. BR-SM-03 menetapkan
// NEEDS_REVISION → RESUBMITTED → UNDER_REVIEW.

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\UnderReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

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

    // Submission yang reviewer-nya masih terpasang langsung masuk antrean
    // review lagi — RESUBMITTED hanya dilewati, bukan diendapkan. Bahwa
    // lompatan status tidak terjadi (BR-SM-03) sudah dibuktikan
    // SubmissionStateTest; yang dibuktikan di sini keadaan akhirnya.
    //
    // Dulu pengantrean ini efek samping
    // FormSubmission::updateStatusBasedOnReviews(), yang dihapus karena
    // memuat jalur auto-reject terlarang (ADR 0006).
    expect($submission->fresh()->status)
        ->toBeInstanceOf(UnderReview::class);
});

it('[SM-SUBMIT-12] resubmit dari DRAFT ditolak', function () {
    [$owner, $submission] = resubmitSliceSubmission(Draft::class);

    expect($owner->can('resubmit', $submission))->toBeFalse();
});

it('[SM-SUBMIT-19] resubmit tanpa field wajib ditolak', function () {
    $owner = User::factory()->create();
    $form = Form::factory()->create();
    $fileType = FieldType::firstOrCreate(['name' => 'file']);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $fileType->id,
        'order' => 1,
    ]);
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $owner->id,
        'status' => NeedsRevision::class,
        'scheme_id' => null,
    ]);

    try {
        ResubmitFormSubmission::run($submission, $owner);
        $this->fail('Resubmit seharusnya ditolak karena proposal PDF belum diunggah.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('field_'.$field->id);
    }

    // Statusnya tidak boleh bergerak saat gerbang menolak.
    expect($submission->fresh()->status)
        ->toBeInstanceOf(NeedsRevision::class);
});
