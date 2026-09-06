<?php

// tests/Feature/Review/ReviewOutcomeWiringTest.php
//
// EvaluateSubmissionOutcome dipanggil dari jalur nyata, bukan hanya ada.
//
// Pemicunya dua: saat ringkasan ditutup (BR-REV-06 → APPROVED), dan saat
// evaluasi terakhir masuk. Tanpa penyambungan ini seluruh BR-REV-05/06
// benar di test tapi tidak pernah berjalan di aplikasi.
//
// Sekalian menutup BR-REV-13: tidak ada status `closed` di domain ini —
// ReviewSummary hanya `open` atau `resolved`.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: FormSubmission, 2: ReviewSummary} */
function outcomeWiringFixture(): array
{
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);

    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

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

    return [$operator, $submission, $summary];
}

it('[REV-OUTCOME-05] menutup ringkasan terakhir menyetujui pengajuan lewat jalur HTTP', function () {
    [$operator, $submission, $summary] = outcomeWiringFixture();

    $this->actingAs($operator)
        ->patch(route('admin.review-summaries.update-status', $summary), ['status' => 'resolved'])
        ->assertRedirect();

    // Seluruh evaluasi sudah selesai dan tidak ada ringkasan terbuka lagi —
    // BR-REV-05 terpenuhi, dan transisinya harus terjadi tanpa aksi tambahan.
    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-03] ringkasan yang masih terbuka menahan persetujuan', function () {
    [$operator, $submission, $summary] = outcomeWiringFixture();

    ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $summary->reviewer_id,
        'status' => 'open',
        'summary_notes' => 'Catatan kedua.',
    ]);

    $this->actingAs($operator)
        ->patch(route('admin.review-summaries.update-status', $summary), ['status' => 'resolved'])
        ->assertRedirect();

    expect($submission->fresh()->status)->not->toBeInstanceOf(Approved::class);
});

it('menolak status closed — tidak ada status itu di domain ini', function () {
    [$operator, , $summary] = outcomeWiringFixture();

    // BR-REV-13: ReviewSummary hanya open atau resolved. Menerima `closed`
    // membuat ringkasan bisa disembunyikan tanpa pernah diselesaikan.
    $this->actingAs($operator)
        ->patch(route('admin.review-summaries.update-status', $summary), ['status' => 'closed'])
        ->assertSessionHasErrors('status');
});
