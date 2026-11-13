<?php

// tests/Feature/Review/ApproveSubmissionTest.php
//
// BR-REV-05, ADR 0018 — APPROVED hanya lewat aksi Setujui Operator, atas pengajuan yang siap
// diputuskan dan tanpa catatan reviewer terbuka. BR-REV-07 — Tolak menunggu syarat yang sama.

use App\Actions\Review\ApproveSubmission;
use App\Actions\Review\RejectSubmission;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\ProposalApprovedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Queries\Review\DecisionReadiness;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
});

function approveOperator(): User
{
    return User::factory()->create()->givePermissionTo(Permission::findOrCreate('submissions.view-all', 'web'));
}

function activeMemberOf(FormSubmission $submission): User
{
    $member = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $member->id])->id,
        'status' => 'active',
    ]);

    return $member;
}

it('[REV-OUTCOME-11] Operator menyetujui pengajuan yang siap diputuskan', function () {
    $submission = readyForDecision();
    $member = activeMemberOf($submission);
    $operator = approveOperator();

    ApproveSubmission::run($submission, $operator);

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class)
        ->and(Activity::query()->where('description', ApproveSubmission::AUDIT_DESCRIPTION)
            ->where('subject_id', $submission->id)->where('causer_id', $operator->id)->exists())->toBeTrue();
    Notification::assertSentTo($submission->submittedBy, ProposalApprovedNotification::class);
    Notification::assertSentTo($member, ProposalApprovedNotification::class);
});

it('[REV-OUTCOME-03] Setujui ditolak selama ada catatan terbuka, menyebut jumlahnya', function () {
    $submission = readyForDecision();
    ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $submission->activeSubmissionReviewers()->value('reviewer_id'),
        'status' => 'open',
        'summary_notes' => 'Catatan yang terlambat.',
    ]);

    try {
        ApproveSubmission::run($submission, approveOperator());
        $this->fail('Setujui seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['approve'][0])->toBe('1 catatan penilai masih terbuka.');
    }

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-OUTCOME-12] selama putaran berjalan Setujui dan Tolak ditolak', function () {
    $submission = outcomeSubmission();
    reviewerOn($submission, 'completed');
    $operator = approveOperator();

    expect($operator->can('approve', $submission))->toBeFalse()
        ->and($operator->can('reject', $submission))->toBeFalse();

    $this->actingAs($operator)->post(route('admin.submissions.approve', $submission))->assertForbidden();
    $this->actingAs($operator)->post(route('admin.submissions.reject', $submission), ['reason' => 'Tidak layak.'])
        ->assertForbidden();

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-OUTCOME-09] fase tanpa review langsung siap diputuskan dan tidak disetujui otomatis', function () {
    $submission = outcomeSubmission(Submitted::class, needsReview: false);

    expect(DecisionReadiness::run($submission)->ready)->toBeTrue()
        ->and($submission->fresh()->status)->toBeInstanceOf(Submitted::class);

    ApproveSubmission::run($submission, approveOperator());

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[REV-OUTCOME-10] fase butuh review tanpa reviewer tidak siap', function () {
    $submission = outcomeSubmission(Submitted::class, needsReview: true);

    $readiness = DecisionReadiness::run($submission);

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->blockers)->toBe(['Belum ada penilai.']);
});

it('[REV-OUTCOME-07] Tolak beralasan pada pengajuan siap diputuskan', function () {
    $submission = readyForDecision();

    RejectSubmission::run($submission, approveOperator(), 'Metodologi tidak memadai.');

    expect($submission->fresh()->status)->toBeInstanceOf(Rejected::class);
    Notification::assertSentTo($submission->submittedBy, ProposalRejectedNotification::class);
});

it('Setujui lewat HTTP memberi pesan sukses', function () {
    $submission = readyForDecision();

    $this->actingAs(approveOperator())
        ->post(route('admin.submissions.approve', $submission))
        ->assertRedirect()
        ->assertSessionHas('success', 'Pengajuan disetujui.');

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('peneliti tidak bisa menyetujui pengajuannya sendiri', function () {
    $submission = readyForDecision();

    expect(fn () => ApproveSubmission::run($submission, $submission->submittedBy))
        ->toThrow(AuthorizationException::class);
});

it('laporan monev tidak pernah siap diputuskan', function () {
    $fx = monevFixture();
    $report = FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'submitted_by' => $fx['owner']->id,
        'status' => Submitted::class,
    ]);

    expect(DecisionReadiness::run($report)->ready)->toBeFalse();
});

it('Setujui dan Tolak menghitung kesiapan keputusan sekali per permintaan', function (string $route, array $payload) {
    $submission = readyForDecision();
    DecisionReadiness::partialMock()->shouldReceive('handle')->once()->passthru();

    $this->actingAs(approveOperator())
        ->post(route($route, $submission), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
})->with([
    'setujui' => ['admin.submissions.approve', []],
    'tolak' => ['admin.submissions.reject', ['reason' => 'Metodologi tidak memadai.']],
]);
