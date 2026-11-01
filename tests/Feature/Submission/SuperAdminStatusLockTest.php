<?php

// tests/Feature/Submission/SuperAdminStatusLockTest.php
//
// Gate::before meloloskan Super Admin dari Policy (ADR 0015), jadi kunci status aksi Operator
// harus dijaga Action-nya sendiri: galat validasi, bukan 500 dan bukan penugasan pada pengajuan
// yang sudah diputuskan (BR-SM-05, BR-REV-01).

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Role;

function lockSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));

    return $admin;
}

function lockSubmission(string $status): FormSubmission
{
    return FormSubmission::factory()->create([
        'is_submitted' => true,
        'status' => $status,
        'scheme_id' => null,
        'form_phase_detail_id' => FormPhaseDetail::factory()->create(['needs_review' => true])->id,
    ]);
}

function lockReviewer(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

it('Super Admin tidak bisa menugaskan reviewer pada pengajuan APPROVED', function () {
    $submission = lockSubmission(Approved::class);

    $this->actingAs(lockSuperAdmin())
        ->post(route('admin.submissions.assign-reviewers', $submission), ['reviewer_ids' => [lockReviewer()->id]])
        ->assertSessionHasErrors('reviewer_id');

    expect($submission->submissionReviewers()->count())->toBe(0)
        ->and($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('Super Admin tidak bisa mengganti reviewer pada pengajuan APPROVED', function () {
    $submission = lockSubmission(Approved::class);
    $outgoing = lockReviewer();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id, 'reviewer_id' => $outgoing->id,
        'evaluation_status' => 'completed', 'status' => 'active',
    ]);

    $this->actingAs(lockSuperAdmin())
        ->post(route('admin.submissions.replace-reviewer', [$submission, $outgoing]), [
            'incoming_reviewer_id' => lockReviewer()->id,
        ])
        ->assertSessionHasErrors('reviewer_id');

    expect($submission->submissionReviewers()->where('status', 'active')->pluck('reviewer_id')->all())
        ->toBe([$outgoing->id]);
});

it('Super Admin tidak bisa menolak pengajuan APPROVED', function () {
    $submission = lockSubmission(Approved::class);

    $this->actingAs(lockSuperAdmin())
        ->post(route('admin.submissions.reject', $submission), ['reason' => 'Plagiarisme terbukti.'])
        ->assertSessionHasErrors('status');

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('Super Admin tidak bisa menarik pengajuan yang belum disetujui', function () {
    $submission = lockSubmission(UnderReview::class);

    $this->actingAs(lockSuperAdmin())
        ->post(route('admin.submissions.withdraw', $submission), ['reason' => 'Duplikasi pendanaan'])
        ->assertSessionHasErrors('status');

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});
