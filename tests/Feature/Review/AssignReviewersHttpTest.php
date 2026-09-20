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
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\Scheme;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Support\Facades\DB;
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

it('menstempel tenggat dari period pengajuan sendiri, bukan period yang berbagi fase', function () {
    // ADR 0004 di level HTTP: submission_period_phases tanpa unique constraint
    // membuat satu FormPhase boleh dimiliki banyak period, jadi menebak period
    // lewat pivot itu menstempel reviewer dengan tenggat milik period lain.
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => 2, 'max_reviewer_workload' => 10],
    ]);

    $form = Form::factory()->create();
    $organization = Organization::create(['name' => 'Organisasi Uji Tenggat HTTP', 'type' => 'faculty']);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $phaseType = PhaseType::create(['name' => 'Tipe Fase Uji Tenggat HTTP']);
    $formPhase = FormPhase::create(['title' => 'Fase Uji Tenggat HTTP', 'is_active' => true]);

    // Period A — milik pengajuan lewat FK submission_period_id.
    $periodA = SubmissionPeriod::create(['name' => 'Periode A Tenggat HTTP']);
    $labelA = SubmissionDateLabel::create(['name' => 'Label A Tenggat HTTP']);
    $dueA = now()->addDays(5)->startOfSecond();
    $dateA = SubmissionDate::create([
        'submission_date_label_id' => $labelA->id,
        'datetime' => $dueA,
        'submission_period_id' => $periodA->id,
    ]);

    // Period B — tak berhubungan lewat FK, tapi berbagi form_phase_id lewat pivot.
    $periodB = SubmissionPeriod::create(['name' => 'Periode B Tenggat HTTP']);
    $labelB = SubmissionDateLabel::create(['name' => 'Label B Tenggat HTTP']);
    SubmissionDate::create([
        'submission_date_label_id' => $labelB->id,
        'datetime' => now()->addDays(20)->startOfSecond(),
        'submission_period_id' => $periodB->id,
    ]);

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $periodB->id,
        'form_phase_id' => $formPhase->id,
    ]);

    $detail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $dateA->id,
        'needs_review' => true,
    ]);

    ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
        'form_phase_detail_id' => $detail->id,
        'submission_period_id' => $periodA->id,
        'status' => Submitted::class,
    ]);

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [httpReviewer()->id],
        ])
        ->assertRedirect();

    $assignment = ReviewerFormAssignment::query()->sole();

    expect($assignment->due_date)->not->toBeNull()
        ->and($assignment->due_date->format('Y-m-d H:i:s'))->toBe($dueA->format('Y-m-d H:i:s'));
});
