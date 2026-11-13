<?php

// tests/Feature/Review/ReviewOutcomeWiringTest.php
//
// BR-REV-16 — tanda Selesai dan kesimpulan putaran tersambung ke jalur HTTP, bukan hanya ada.
// Mengirim lembar dan menutup catatan tidak lagi memicu hasil (ADR 0018); satu-satunya pemicu
// adalah peserta terakhir yang menekan Selesai lewat `reviewer.assignments.finish`.
//
// Jalur status ulir selain resolve sudah dicabut: lihat ReviewSummaryStatusEndpointRemovedTest.

use App\Models\FieldType;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormField;
use App\Models\ReviewRound;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

function wiringReviewer(SubmissionReviewer $seat): User
{
    return $seat->reviewer->user->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
}

function wiringSubmitEvaluation(User $reviewer, ReviewerFormAssignment $assignment): void
{
    $field = ReviewFormField::create([
        'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'label' => 'Kelayakan',
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'is_required' => false,
        'order' => 1,
    ]);

    test()->actingAs($reviewer)
        ->post(route('reviewer.evaluation-form.submit', $assignment), ['responses' => [$field->id => 'Layak']])
        ->assertSessionHasNoErrors();
}

it('[REV-OUTCOME-01] peserta terakhir menekan Selesai menilai lewat HTTP membuat pengajuan siap', function () {
    $submission = outcomeSubmission();
    $seat = reviewerOn($submission, 'completed');

    $this->actingAs(wiringReviewer($seat))
        ->post(route('reviewer.assignments.finish', $submission))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_READY);
});

it('[REV-OUTCOME-04] peserta terakhir dengan catatan terbuka meminta revisi lewat HTTP', function () {
    $submission = outcomeSubmission();
    $seat = reviewerOn($submission, 'completed');
    openSummaryOn($submission, $seat);

    $this->actingAs(wiringReviewer($seat))
        ->post(route('reviewer.assignments.finish', $submission))
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class);
});

it('[REV-OUTCOME-13] mengirim lembar terakhir lewat HTTP tidak memutuskan apa pun', function () {
    $fx = reviewedDetailFixture('+1 month', '+2 months');

    wiringSubmitEvaluation($fx['reviewer'], $fx['assignment']);

    expect($fx['submission']->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($fx['submission']->runningReviewRound())->not->toBeNull();
});

it('[REV-OUTCOME-14] Selesai menilai sebelum lembar terkirim ditolak dengan pesan', function () {
    $fx = reviewedDetailFixture('+1 month', '+2 months');

    $this->actingAs($fx['reviewer'])
        ->post(route('reviewer.assignments.finish', $fx['submission']))
        ->assertSessionHasErrors(['round' => 'Kirim lembar penilaian lebih dulu.']);
});

it('reviewer yang tidak ditugaskan ditolak 403', function () {
    $submission = outcomeSubmission();
    reviewerOn($submission, 'completed');
    $outsider = User::factory()->create()->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));

    $this->actingAs($outsider)
        ->post(route('reviewer.assignments.finish', $submission))
        ->assertForbidden();

    expect($submission->latestReviewRound()->isRunning())->toBeTrue();
});

it('reviewer bukan peserta putaran pemeriksaan ditolak 422', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    $b = reviewerOn($submission, 'completed');
    // Putaran pemeriksaan hanya berisi A (pemilik catatan); B tetap bertugas tapi bukan peserta.
    $submission->latestReviewRound()->update(['completed_at' => now(), 'outcome' => ReviewRound::OUTCOME_NEEDS_REVISION]);
    $submission->reviewRounds()->create(['number' => 2, 'kind' => 'recheck'])
        ->participants()->create(['submission_reviewer_id' => $a->id, 'kind' => 'recheck']);

    $this->actingAs(wiringReviewer($b))
        ->from(route('reviewer.assignments.summary', $submission))
        ->post(route('reviewer.assignments.finish', $submission))
        ->assertSessionHasErrors(['round' => 'Anda bukan peserta putaran penilaian ini.']);
});

it('pengajuan tanpa putaran (bukan milik reviewer) tidak bocor', function () {
    $other = FormSubmission::factory()->create();
    $submission = outcomeSubmission();
    $seat = reviewerOn($submission, 'completed');

    $this->actingAs(wiringReviewer($seat))
        ->post(route('reviewer.assignments.finish', $other))
        ->assertForbidden();
});
