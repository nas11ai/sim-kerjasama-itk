<?php

// tests/Feature/Notification/NotificationDestinationTest.php
//
// BR-NOT-04, W39 — "Buka" membawa penerima ke halaman yang relevan. Tujuannya
// dihitung saat diklik, jadi akses ke pengajuannya dicek ulang saat itu
// (BR-SM-07): notifikasi lama tidak boleh menjadi pintu ke data yang kini
// tertutup bagi penerimanya.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AccountReactivatedNotification;
use App\Notifications\ExportCompletedNotification;
use App\Notifications\FormChangedNotification;
use App\Notifications\MonevEvaluationSubmittedNotification;
use App\Notifications\MonevStageOpenedNotification;
use App\Notifications\NewRegistrationAwaitingVerification;
use App\Notifications\NidnVerifiedNotification;
use App\Notifications\ReviewerAppointedNotification;
use App\Notifications\ReviewerAssignedNotification;
use App\Notifications\ReviewThreadOpenedNotification;
use App\Notifications\SubmissionPeriodClosedNotification;
use App\Queries\Notification\NotificationDestination;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/** @param array<string, int|string|null> $data */
function ndNotification(User $recipient, string $class, array $data): DatabaseNotification
{
    return $recipient->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $class,
        'data' => ['title' => 't', 'message' => 'm', ...$data],
    ]);
}

/** @param array<string, int|string|null> $data */
function ndDestination(User $recipient, string $class, array $data): ?string
{
    return NotificationDestination::run(ndNotification($recipient, $class, $data), $recipient);
}

function ndReviewerOf(FormSubmission $submission, string $status = 'active'): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));

    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => $status,
    ]);

    return $user;
}

function ndSubmissionContext(FormSubmission $submission): array
{
    return ['type' => 'review_thread_opened', 'form_submission_id' => $submission->id];
}

it('reviewer_assigned membuka form evaluasi', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = ndReviewerOf($submission);

    expect(ndDestination($reviewer, ReviewerAssignedNotification::class, [
        'type' => 'reviewer_assigned',
        'form_submission_id' => $submission->id,
    ]))->toBe(route('reviewer.assignments.evaluation', $submission));
});

it('notifikasi pengajuan membuka halaman pemilik bagi pemiliknya', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $owner->id]);

    expect(ndDestination($owner, ReviewThreadOpenedNotification::class, ndSubmissionContext($submission)))
        ->toBe(route('user.submissions.show', $submission));
});

it('anggota tim aktif dibawa ke halaman pemilik (BR-SM-07)', function () {
    $member = User::factory()->create();
    $submission = FormSubmission::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $member->id])->id,
        'status' => 'active',
    ]);

    expect(ndDestination($member, ReviewThreadOpenedNotification::class, [
        'type' => 'review_thread_opened',
        'form_submission_id' => $submission->id,
    ]))->toBe(route('user.submissions.show', $submission));
});

it('pemegang submissions.view-all dibawa ke halaman Operator', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo(Permission::findOrCreate('submissions.view-all', 'web'));
    $submission = FormSubmission::factory()->create();

    expect(ndDestination($operator, ReviewThreadOpenedNotification::class, [
        'type' => 'proposal_submitted',
        'form_submission_id' => $submission->id,
    ]))->toBe(route('admin.submissions.show', $submission));
});

it('reviewer aktif dibawa ke ringkasan review', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = ndReviewerOf($submission);

    expect(ndDestination($reviewer, ReviewThreadOpenedNotification::class, ndSubmissionContext($submission)))
        ->toBe(route('reviewer.assignments.summary', $submission));
});

it('penugasan yang diganti tidak membuka pengajuan', function () {
    // BR-REV-10 — reviewer yang diganti kehilangan aksesnya; notifikasi lama
    // tidak boleh mengembalikannya.
    $submission = FormSubmission::factory()->create();
    $replaced = ndReviewerOf($submission, 'replaced');

    expect(ndDestination($replaced, ReviewerAssignedNotification::class, [
        'type' => 'reviewer_assigned',
        'form_submission_id' => $submission->id,
    ]))->toBeNull();
});

it('orang luar tidak dibawa ke pengajuan', function () {
    $submission = FormSubmission::factory()->create();

    expect(ndDestination(User::factory()->create(), ReviewThreadOpenedNotification::class, ndSubmissionContext($submission)))
        ->toBeNull();
});

it('pengajuan yang sudah tidak ada tidak dibuka', function () {
    expect(ndDestination(User::factory()->create(), ReviewThreadOpenedNotification::class, [
        'type' => 'review_thread_opened',
        'form_submission_id' => 999999,
    ]))->toBeNull();
});

it('notifikasi periode membuka daftar pengajuan', function () {
    expect(ndDestination(User::factory()->create(), SubmissionPeriodClosedNotification::class, [
        'type' => 'submission_period_closed',
        'submission_period_id' => 1,
    ]))->toBe(route('user.submissions.index'));
});

it('form_changed membuka daftar pengajuan', function () {
    expect(ndDestination(User::factory()->create(), FormChangedNotification::class, ['type' => 'form_changed', 'form_id' => 1]))
        ->toBe(route('user.submissions.index'));
});

it('nidn_* membuka status verifikasi', function () {
    expect(ndDestination(User::factory()->create(), NidnVerifiedNotification::class, ['type' => 'nidn_verified']))
        ->toBe(route('verification.status'));
});

it('pendaftaran baru membuka antrian verifikasi', function () {
    expect(ndDestination(User::factory()->create(), NewRegistrationAwaitingVerification::class, [
        'type' => 'new_registration_awaiting_verification',
        'user_id' => 1,
    ]))->toBe(route('admin.verifications.index'));
});

it('export_* membuka riwayat ekspor', function () {
    expect(ndDestination(User::factory()->create(), ExportCompletedNotification::class, ['type' => 'export_completed']))
        ->toBe(route('exports.index'));
});

it('reviewer_appointed membuka daftar penugasan', function () {
    expect(ndDestination(User::factory()->create(), ReviewerAppointedNotification::class, ['type' => 'reviewer_appointed']))
        ->toBe(route('reviewer.assignments.index'));
});

it('[IAM-REACT-01] account_reactivated membuka dasbor', function () {
    expect(ndDestination(User::factory()->create(), AccountReactivatedNotification::class, ['type' => 'account_reactivated']))
        ->toBe(route('dashboard'));
});

it('account_deactivated dan tipe tak dikenal tidak punya tujuan', function () {
    $user = User::factory()->create();

    expect(ndDestination($user, NidnVerifiedNotification::class, ['type' => 'account_deactivated']))->toBeNull()
        ->and(ndDestination($user, NidnVerifiedNotification::class, ['type' => 'entah_apa']))->toBeNull()
        ->and(ndDestination($user, NidnVerifiedNotification::class, []))->toBeNull();
});

it('[MON-REVIEW-12] notifikasi evaluasi monev membuka detail laporan', function () {
    $fx = monevFixture();
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $reviewer = ndReviewerOf($laporan);

    $data = ['type' => 'monev_evaluation_submitted', 'form_submission_id' => $laporan->id];

    expect(ndDestination($fx['owner'], MonevEvaluationSubmittedNotification::class, $data))
        ->toBe(route('monev.reports.show', $laporan))
        ->and(ndDestination($reviewer, MonevEvaluationSubmittedNotification::class, $data))
        ->toBe(route('reviewer.assignments.summary', $laporan));
});

it('[MON-REVIEW-10] notifikasi siklus dibuka membuka Daftar Monev bagi peneliti', function () {
    $fx = monevFixture();
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $reviewer = ndReviewerOf($laporan);
    $operator = User::factory()->create();
    $operator->givePermissionTo(Permission::findOrCreate('submissions.view-all', 'web'));
    $data = [
        'type' => 'monev_stage_opened',
        'form_submission_id' => $fx['parent']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
    ];

    expect(ndDestination($fx['owner'], MonevStageOpenedNotification::class, $data))->toBe(route('monev.index'))
        ->and(ndDestination($reviewer, MonevStageOpenedNotification::class, $data))->toBe(route('reviewer.assignments.index'))
        ->and(ndDestination($operator, MonevStageOpenedNotification::class, $data))->toBe(route('monev.manage.show', $fx['parent']))
        ->and(ndDestination(User::factory()->create(), MonevStageOpenedNotification::class, $data))->toBeNull();
});
