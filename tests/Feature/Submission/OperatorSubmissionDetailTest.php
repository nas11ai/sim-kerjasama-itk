<?php

// OPERATOR-02-02 — detail pengajuan bagi pemegang submissions.view-all: isi yang sama dengan
// detail peneliti, ditambah reviewer, lembar evaluasi (W22), ulir, dan aksi Operator (ADR 0015).

use App\Models\BudgetLineItem;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** @param  list<string>  $permissions */
function detailOperator(array $permissions = ['submissions.view-all', 'reviewers.assign']): User
{
    $operator = User::factory()->create();

    foreach ($permissions as $permission) {
        $operator->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $operator;
}

function detailSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));

    return $admin;
}

function sentFor(string $status, ?FormPhaseDetail $detail = null): FormSubmission
{
    return FormSubmission::factory()->create([
        'is_submitted' => true,
        'status' => $status,
        'form_phase_detail_id' => $detail?->id,
    ]);
}

function seatReviewer(FormSubmission $submission, string $status = 'active', string $name = 'Reviewer Uji'): SubmissionReviewer
{
    $user = User::factory()->create(['name' => $name]);

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $user->id])->id,
        'evaluation_status' => 'completed',
        'status' => $status,
    ]);
}

function joinTeam(FormSubmission $submission, string $role, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return $user;
}

function submittedSheet(SubmissionReviewer $seat): void
{
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => FormPhaseDetail::factory()->create()->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);
    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $seat->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);
    ReviewFormResponse::create(['reviewer_form_assignment_id' => $assignment->id, 'status' => 'submitted', 'submitted_at' => now()]);
}

function showOperatorPage(User $viewer, FormSubmission $submission): TestResponse
{
    return test()->actingAs($viewer)->get(route('admin.submissions.show', $submission));
}

it('pengajuan APPROVED tidak menawarkan penugasan reviewer', function () {
    showOperatorPage(detailOperator(), sentFor(Approved::class))
        ->assertInertia(fn (Assert $page) => $page
            ->where('operatorCan.assignReviewers', false)
            ->where('operatorCan.replaceReviewer', false));
});

it('[SM-ACCESS-04] pemegang view-all membuka Submission/ManageShowPage', function () {
    showOperatorPage(detailOperator(), sentFor(UnderReview::class))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/ManageShowPage')
            ->has('submission')
            ->has('reviewers')
            ->has('evaluationSheets')
            ->has('threads')
            ->has('overrideTarget')
            ->has('operatorCan')
            ->where('operatorCan.assignReviewers', true)
            ->where('operatorCan.transferOwnership', true)
            ->missing('canCreateThread')
            ->missing('targetOutputTypes'));
});

it('draft dijawab 404', function () {
    showOperatorPage(detailOperator(), FormSubmission::factory()->create(['status' => Draft::class]))
        ->assertNotFound();
});

it('peneliti tanpa view-all ditolak', function () {
    showOperatorPage(User::factory()->create(), sentFor(UnderReview::class))->assertForbidden();
});

it('[SM-SUBMIT-14] Super Admin membuka detail dan bisa menarik pengajuan APPROVED', function () {
    showOperatorPage(detailSuperAdmin(), sentFor(Approved::class))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('operatorCan.withdraw', true));
});

it('[SM-WITHDRAW-03] pengajuan UNDER_REVIEW tidak bisa ditarik', function () {
    showOperatorPage(detailOperator(), sentFor(UnderReview::class))
        ->assertInertia(fn (Assert $page) => $page
            ->where('operatorCan.withdraw', false)
            ->where('operatorCan.reject', true));
});

it('[SM-WITHDRAW-04] pengajuan WITHDRAWN tetap membawa anggaran, anggota, dan berkas', function () {
    $submission = sentFor(Withdrawn::class);
    BudgetLineItem::factory()->create(['form_submission_id' => $submission->id]);
    joinTeam($submission, ResearchMember::ROLE_MEMBER, 'Anggota Uji');
    $field = FormField::create([
        'form_id' => $submission->form_id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'label' => 'Proposal',
        'is_required' => false,
        'order' => 1,
    ]);
    FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => 'form-uploads/proposal.pdf']);

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->has('budget.items', 1)
            ->has('members', 2)
            ->where('answers.0.isFile', true));
});

it('[BUD-LOCK-03] anggaran APPROVED tetap baca-saja bagi Super Admin', function () {
    showOperatorPage(detailSuperAdmin(), sentFor(Approved::class))
        ->assertInertia(fn (Assert $page) => $page->where('budget.editable', false));
});

it('[SM-STATE-05] perpanjangan tetap tersedia di status terminal', function () {
    showOperatorPage(detailOperator(), sentFor(Approved::class))
        ->assertInertia(fn (Assert $page) => $page->where('operatorCan.grantOverride', true));
});

it('[REV-ASSIGN-02] satu reviewer dari minimal dua', function () {
    $submission = sentFor(Submitted::class);
    seatReviewer($submission);

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeReviewerCount', 1)
            ->where('minimumReviewers', 2)
            ->where('submission.status.key', 'submitted'));
});

it('[REV-OUTCOME-10] fase butuh review tanpa reviewer', function () {
    $submission = sentFor(Submitted::class, FormPhaseDetail::factory()->create(['needs_review' => true]));

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->where('needsReview', true)
            ->where('activeReviewerCount', 0)
            ->where('submission.status.key', 'submitted'));
});

it('[REV-REASSIGN-01] reviewer yang digantikan tetap terdaftar', function () {
    $submission = sentFor(UnderReview::class);
    seatReviewer($submission, 'replaced', 'Reviewer Lama');
    seatReviewer($submission, 'active', 'Reviewer Pengganti');

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviewers', 2)
            ->where('reviewers.0.name', 'Reviewer Pengganti')
            ->where('reviewers.0.replaced', false)
            ->where('reviewers.1.name', 'Reviewer Lama')
            ->where('reviewers.1.replaced', true)
            ->where('reviewers.1.evaluationLabel', 'Selesai')
            ->where('activeReviewerCount', 1));
});

it('[REV-REASSIGN-02] evaluasi reviewer yang digantikan tetap terbaca', function () {
    $submission = sentFor(UnderReview::class);
    submittedSheet(seatReviewer($submission, 'replaced', 'Reviewer Lama'));
    submittedSheet(seatReviewer($submission, 'active', 'Reviewer Pengganti'));

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->has('evaluationSheets', 2)
            ->where('evaluationSheets', fn ($sheets) => collect($sheets)->contains(
                fn ($sheet) => $sheet['reviewer'] === 'Reviewer Lama' && $sheet['replaced'] === true,
            )));
});

it('[SM-OWNER-02] member biasa tidak ditawarkan sebagai penerima', function () {
    $submission = sentFor(UnderReview::class);
    $co = joinTeam($submission, ResearchMember::ROLE_CO_INVESTIGATOR, 'Sari Co');
    joinTeam($submission, ResearchMember::ROLE_MEMBER, 'Anggota Biasa');

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->has('ownershipCandidates', 1)
            ->where('ownershipCandidates.0.userId', $co->id)
            ->where('ownershipCandidates.0.name', 'Sari Co'));
});

it('operatorCan mengikuti reviewers.assign', function () {
    showOperatorPage(detailOperator(['submissions.view-all']), sentFor(UnderReview::class))
        ->assertInertia(fn (Assert $page) => $page
            ->where('operatorCan.assignReviewers', false)
            ->where('operatorCan.replaceReviewer', false)
            ->where('operatorCan.reject', true));
});

it('target perpanjangan menyebut fase dan reviewer yang evaluasinya lewat tenggat', function () {
    $detail = FormPhaseDetail::factory()->create();
    $submission = sentFor(UnderReview::class, $detail);
    $late = seatReviewer($submission, 'active', 'Reviewer Telat');
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $late->id, 'review_evaluation_form_id' => $form->id,
        'is_required' => true, 'assigned_at' => now()->subWeek(), 'due_date' => now()->subDay(), 'is_active' => true,
    ]);
    seatReviewer($submission, 'active', 'Reviewer Tepat');
    $detail->load('formPhase', 'submissionDate');

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page
            ->where('overrideTarget.phaseLabel', $detail->formPhase->title.' — '.$detail->submissionDate->label())
            ->where('overrideTarget.affectedReviewers', ['Reviewer Telat']));
});

it('[FE-WINDOW-07] reviewer terdampak hanya yang form evaluasinya melekat pada detail pengajuan ini', function () {
    $detail = FormPhaseDetail::factory()->create();
    $submission = sentFor(UnderReview::class, $detail);
    $elsewhere = seatReviewer($submission, 'active', 'Reviewer Detail Lain');
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi Monev', 'form_phase_detail_id' => FormPhaseDetail::factory()->create()->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $elsewhere->id, 'review_evaluation_form_id' => $form->id,
        'is_required' => true, 'assigned_at' => now()->subWeek(), 'due_date' => now()->subDay(), 'is_active' => true,
    ]);

    showOperatorPage(detailOperator(), $submission)
        ->assertInertia(fn (Assert $page) => $page->where('overrideTarget.affectedReviewers', []));
});
