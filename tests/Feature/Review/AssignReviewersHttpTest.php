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

use App\Models\Form;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
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

it('mencatat satu entri audit untuk satu permintaan penugasan', function () {
    [$operator, $submission] = assignHttpFixture();

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [httpReviewer()->id, httpReviewer()->id],
        ])
        ->assertRedirect();

    expect(Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $submission->getMorphClass())
        ->where('subject_id', $submission->id)
        ->where('description', 'Penugasan reviewer diproses')
        ->count())->toBe(1);
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

it('[REV-TENURE-06] menugaskan reviewer yang end_date-nya hari ini berhasil', function () {
    [$operator, $submission] = assignHttpFixture();

    $lastDay = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subYear(),
        'end_date' => now()->toDateString(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$lastDay->id],
        ])
        ->assertSessionHasNoErrors();

    expect($submission->submissionReviewers()->count())->toBe(1);
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

it('menstempel tenggat dari detail pemilik form, bukan tanggal terakhir period', function () {
    // BR-FE-17 di level HTTP. Jalur lama menebak period lewat pivot
    // submission_period_phases lalu mengambil tanggal TERAKHIR-nya — tenggat
    // milik tahap lain, dan milik period lain (ADR 0004).
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => 2, 'max_reviewer_workload' => 10],
    ]);

    $fx = phaseSharedByTwoPeriods('Uji Tenggat HTTP', needsReview: true);

    ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $fx['detail']->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'submission_period_id' => $fx['periodA']->id,
        'status' => Submitted::class,
    ]);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [httpReviewer()->id],
        ])
        ->assertRedirect();

    $assignment = ReviewerFormAssignment::query()->sole();

    expect($assignment->due_date)->not->toBeNull()
        ->and($assignment->due_date->format('Y-m-d H:i:s'))->toBe($fx['dueA']->format('Y-m-d H:i:s'))
        ->and($assignment->due_date->format('Y-m-d H:i:s'))->not->toBe($fx['dueB']->format('Y-m-d H:i:s'));
});

it('[REV-ASSIGN-09] mengirim satu notifikasi penugasan, bukan dua', function () {
    // Skenarionya menyebut satu notifikasi ReviewerAssigned. Jalur HTTP dulu
    // menambah satu email Mail custom di atasnya — dan hanya kalau fase-nya
    // punya form evaluasi, jadi jumlahnya bergantung pada hal yang tak terkait.
    Notification::fake();
    Mail::fake();

    [$operator, $submission] = assignHttpFixture();

    // Wajib ada: tanpa form evaluasi, `continue` di assignReviewers() keburu
    // menyelamatkan jalurnya dan email keduanya tidak pernah tersentuh.
    ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $reviewer = httpReviewer();

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$reviewer->id],
        ])
        ->assertRedirect();

    Notification::assertSentToTimes($reviewer->user, ReviewerAssignedNotification::class, 1);
    Mail::assertNothingQueued();
});

it('tidak pernah mengarang tenggat dari jam dinding', function () {
    // Jalur lama jatuh ke `new DateTime('+7 days')` saat periodenya tak
    // ketemu. Sekarang tenggatnya selalu tanggal milik detail pemilik form,
    // jadi tak ada lagi tanggal yang lahir dari waktu penugasan.
    [$operator, $submission] = assignHttpFixture();

    ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $submission->update(['submission_period_id' => SubmissionPeriod::create(['name' => 'Periode Tanpa Tanggal'])->id]);

    // Digeser menjauh dari +7 hari: factory-nya kebetulan memakai addWeek(),
    // dan tanpa ini assertion di bawah lulus karena kebetulan, bukan karena benar.
    $submission->formPhaseDetail->submissionDate->update(['datetime' => now()->addDays(11)->startOfSecond()]);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [httpReviewer()->id],
        ])
        ->assertRedirect();

    $assignment = ReviewerFormAssignment::query()->sole();
    $detailDate = $submission->formPhaseDetail->submissionDate->fresh()->datetime;

    expect($assignment->due_date->format('Y-m-d H:i:s'))->toBe($detailDate->format('Y-m-d H:i:s'))
        ->and($assignment->due_date->format('Y-m-d H:i:s'))->not->toBe(now()->addDays(7)->format('Y-m-d H:i:s'));
});
