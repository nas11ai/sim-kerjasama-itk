<?php

// tests/Feature/Review/ReviewerAssignmentIndexTest.php
//
// REVIEWER-03-01 — satu kartu per penugasan reviewer pada pengajuan, dipilah "Perlu Tindakan"
// dan "Selesai". Hanya penugasan yang masih berlaku yang terdaftar (BR-SM-07, BR-REV-10).

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create()->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
    $this->reviewer = Reviewer::factory()->create(['user_id' => $this->user->id]);
});

function assignmentIndexCard(Reviewer $reviewer, string $evaluation = 'pending', string $status = 'active'): SubmissionReviewer
{
    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => FormPhaseDetail::factory()->create(['needs_review' => true])->id,
        'submitted_by' => User::factory()->create(['name' => 'Dr. Andi Mappa'])->id,
        'status' => UnderReview::class,
    ]);

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => $status,
    ]);
}

function assignmentIndexVisit(User $user, array $query = []): TestResponse
{
    return test()->actingAs($user)->get(route('reviewer.assignments.index', $query));
}

it('reviewer membuka Review/AssignmentIndexPage dengan penugasan perlu tindakan', function () {
    $pending = assignmentIndexCard($this->reviewer);
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $pending->formSubmission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $pending->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'due_date' => now()->addDays(3),
        'is_active' => true,
    ]);
    assignmentIndexCard($this->reviewer, 'completed');

    assignmentIndexVisit($this->user)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/AssignmentIndexPage')
            ->where('filter', 'action')
            ->has('needsAction', 1)
            ->where('needsAction.0.submissionId', $pending->form_submission_id)
            ->where('needsAction.0.state', 'evaluation_pending')
            ->where('needsAction.0.stateLabel', 'Evaluasi Pending')
            ->where('needsAction.0.leadName', 'Dr. Andi Mappa')
            ->whereNot('needsAction.0.dueDate', null)
            ->where('done', []));
});

it('ulir terbuka milik reviewer menjadikan kartu butuh respon diskusi', function () {
    $settled = assignmentIndexCard($this->reviewer, 'completed');
    ReviewSummary::create([
        'form_submission_id' => $settled->form_submission_id,
        'reviewer_id' => $this->reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);

    assignmentIndexVisit($this->user)
        ->assertInertia(fn (Assert $page) => $page
            ->has('needsAction', 1)
            ->where('needsAction.0.state', 'discussion_open')
            ->where('needsAction.0.stateLabel', 'Butuh Respon Diskusi'));
});

it('filter all menyertakan penugasan selesai', function () {
    $done = assignmentIndexCard($this->reviewer, 'not_required');

    assignmentIndexVisit($this->user, ['filter' => 'all'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'all')
            ->has('needsAction', 0)
            ->has('done', 1)
            ->where('done.0.submissionId', $done->form_submission_id)
            ->where('done.0.state', 'done'));
});

it('[REV-REASSIGN-03] penugasan yang digantikan tidak terdaftar, penggantinya terdaftar', function () {
    $replaced = assignmentIndexCard($this->reviewer, 'pending', 'replaced');
    $successorUser = User::factory()->create()->givePermissionTo('reviewers.evaluate');
    $successor = SubmissionReviewer::create([
        'form_submission_id' => $replaced->form_submission_id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $successorUser->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    assignmentIndexVisit($this->user, ['filter' => 'all'])
        ->assertInertia(fn (Assert $page) => $page->has('needsAction', 0)->has('done', 0));

    assignmentIndexVisit($successorUser)
        ->assertInertia(fn (Assert $page) => $page
            ->has('needsAction', 1)
            ->where('needsAction.0.submissionId', $successor->form_submission_id));
});

it('penugasan reviewer lain tidak terdaftar', function () {
    assignmentIndexCard(Reviewer::factory()->create(['user_id' => User::factory()->create()->id]));

    assignmentIndexVisit($this->user, ['filter' => 'all'])
        ->assertInertia(fn (Assert $page) => $page->has('needsAction', 0)->has('done', 0));
});

it('pengguna tanpa reviewers.evaluate ditolak', function () {
    assignmentIndexVisit(User::factory()->create())->assertForbidden();
});

it('daftar reviewer legacy dialihkan ke penugasan', function () {
    $this->actingAs($this->user)
        ->get(route('reviewer.submissions.index'))
        ->assertRedirect(route('reviewer.assignments.index'));
});
