<?php

// tests/Feature/Review/AssignReviewersHttpTest.php
//
// Jalur HTTP penugasan reviewer mendelegasikan ke AssignReviewer.
//
// Sebelum irisan ini, ReviewController::assignReviewers() memakai
// firstOrCreate() polos: nol pemeriksaan konflik kepentingan (BR-REV-02),
// nol pemeriksaan masa tugas (BR-REV-09), duplikat lolos diam-diam
// (BR-REV-03), dan status ditentukan updateStatusBasedOnReviews() yang
// bukan BR-REV-01.
//
// Delegasi diuji sebagai perilaku, bukan lewat mock: kalau controller
// diam-diam berhenti memanggil Action-nya, test di sini memerah.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: FormSubmission} */
function assignHttpFixture(int $minReviewers = 2): array
{
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => $minReviewers, 'max_reviewer_workload' => 10],
    ]);

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
        'form_phase_detail_id' => $detail->id,
        'status' => Submitted::class,
    ]);

    return [$operator, $submission];
}

function httpReviewer(?int $userId = null): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => $userId ?? User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

it('menugaskan reviewer lewat endpoint dan memindahkan status saat minimum tercapai', function () {
    [$operator, $submission] = assignHttpFixture();

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [httpReviewer()->id, httpReviewer()->id],
        ])
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->submissionReviewers()->count())->toBe(2);
});

it('[REV-ASSIGN-04] menolak ketua pengaju lewat jalur HTTP', function () {
    [$operator, $submission] = assignHttpFixture();

    $owner = httpReviewer($submission->submitted_by);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$owner->id],
        ])
        ->assertSessionHasErrors();

    expect($submission->submissionReviewers()->count())->toBe(0);
});

it('[REV-ASSIGN-08] menolak reviewer di luar masa tugas lewat jalur HTTP', function () {
    [$operator, $submission] = assignHttpFixture();

    $expired = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subYear(),
        'end_date' => now()->subDay(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$expired->id],
        ])
        ->assertSessionHasErrors();

    expect($submission->submissionReviewers()->count())->toBe(0);
});

it('[REV-ASSIGN-05] menolak reviewer yang sudah ditugaskan, bukan diam-diam melewatinya', function () {
    [$operator, $submission] = assignHttpFixture(minReviewers: 3);

    $reviewer = httpReviewer();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$reviewer->id],
        ])
        ->assertSessionHasErrors();

    // firstOrCreate lama akan menyisakan tepat satu baris tanpa mengeluh.
    // Yang membedakan: sekarang penolakannya terlihat.
    expect($submission->submissionReviewers()->count())->toBe(1);
});

it('satu penugasan yang ditolak membatalkan seluruh permintaan', function () {
    [$operator, $submission] = assignHttpFixture();

    $good = httpReviewer();
    $owner = httpReviewer($submission->submitted_by);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$good->id, $owner->id],
        ])
        ->assertSessionHasErrors();

    // Menyimpan sebagian akan menyisakan penugasan yang Operator kira gagal.
    expect($submission->submissionReviewers()->count())->toBe(0)
        ->and($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});
