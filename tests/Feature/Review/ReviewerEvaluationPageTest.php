<?php

// tests/Feature/Review/ReviewerEvaluationPageTest.php
//
// REVIEWER-03-03 — kontrak props `Review/EvaluationPage` dan tenggat evaluasi (BR-FE-17).
// Aturan isi evaluasinya sendiri sudah terikat di EvaluationResponseTest dan EvaluationLockTest.

use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Cache::flush();

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $this->submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => User::factory()->create(['name' => 'Dr. Budi Santoso'])->id,
        'status' => UnderReview::class,
    ]);
    $this->user = evaluationPageReviewerUser();
    $this->assignment = SubmissionReviewer::create([
        'form_submission_id' => $this->submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $this->user->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    $this->substance = evaluationPageForm($detail, 'Form Substansi', 1);
    $this->administration = evaluationPageForm($detail, 'Form Administrasi', 2);
    $this->field = ReviewFormField::create([
        'review_evaluation_form_id' => $this->administration->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'select'])->id,
        'label' => 'Metodologi (1–7)',
        'is_required' => true,
        'order' => 1,
    ]);
    ReviewFormFieldOption::create(['review_form_field_id' => $this->field->id, 'label' => '5 — Baik', 'value' => '5', 'order' => 1]);
});

function evaluationPageReviewerUser(): User
{
    return User::factory()->create()->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
}

function evaluationPageForm(FormPhaseDetail $detail, string $title, int $order): ReviewEvaluationForm
{
    return ReviewEvaluationForm::create([
        'title' => $title,
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => $order,
    ]);
}

function evaluationPageStarted(SubmissionReviewer $assignment, ReviewEvaluationForm $form, ?string $status, mixed $due = null): ReviewerFormAssignment
{
    $started = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $assignment->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'due_date' => $due ?? now()->addWeek(),
        'is_active' => true,
    ]);

    if ($status !== null) {
        ReviewFormResponse::create(['reviewer_form_assignment_id' => $started->id, 'status' => $status, 'submitted_at' => $status === 'submitted' ? now() : null]);
    }

    return $started;
}

it('[SM-ACCESS-05] reviewer yang ditugaskan membuka Review/EvaluationPage', function () {
    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.evaluation', $this->submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/EvaluationPage')
            ->where('submission.id', $this->submission->id)
            ->where('submission.leadName', 'Dr. Budi Santoso')
            ->has('answers')
            ->has('forms', 2)
            ->where('forms.0.title', 'Form Substansi')
            ->where('forms.0.state', 'not_started')
            ->where('forms.0.assignmentId', null)
            ->where('forms.0.canEdit', true)
            ->where('activeFormId', $this->substance->id)
            ->where('evaluationSettled', false));
});

it('form draft membawa field dan jawaban tersimpan', function () {
    $started = evaluationPageStarted($this->assignment, $this->administration, 'draft');
    $started->reviewFormResponse?->saveFieldResponse($this->field->id, '5');

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.evaluation', [$this->submission, 'form' => $this->administration->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeFormId', $this->administration->id)
            ->where('forms.1.state', 'draft')
            ->where('forms.1.assignmentId', $started->id)
            ->where('forms.1.canEdit', true)
            ->where('forms.1.fields.0.type', 'select')
            ->where('forms.1.fields.0.value', '5')
            ->where('forms.1.fields.0.options.0', ['value' => '5', 'label' => '5 — Baik']));
});

it('[REV-EVAL-02] form yang sudah disubmit terkunci', function () {
    evaluationPageStarted($this->assignment, $this->substance, 'submitted');

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.evaluation', $this->submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('forms.0.state', 'submitted')
            ->where('forms.0.canEdit', false)
            ->whereNot('forms.0.submittedAt', null)
            // Tab awal jatuh ke form pertama yang belum dikirim.
            ->where('activeFormId', $this->administration->id));
});

it('membuka halaman tidak meninggalkan draft respons', function () {
    evaluationPageStarted($this->assignment, $this->substance, null);

    $this->actingAs($this->user)->get(route('reviewer.assignments.evaluation', $this->submission))->assertOk();

    expect(ReviewFormResponse::count())->toBe(0);
});

it('[SM-ACCESS-06] reviewer yang tidak ditugaskan ditolak di halaman evaluasi', function () {
    $stranger = evaluationPageReviewerUser();
    Reviewer::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->get(route('reviewer.assignments.evaluation', $this->submission))
        ->assertForbidden();
});

it('[SM-ACCESS-06] reviewer yang digantikan ditolak di halaman evaluasi', function () {
    $this->assignment->markAsReplaced();

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.evaluation', $this->submission))
        ->assertForbidden();
});

it('pengguna tanpa reviewers.evaluate ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reviewer.assignments.evaluation', $this->submission))
        ->assertForbidden();
});

it('memulai form kembali ke tab form itu', function () {
    $this->actingAs($this->user)
        ->post(route('reviewer.evaluation-form.start'), [
            'submission_id' => $this->submission->id,
            'review_evaluation_form_id' => $this->administration->id,
        ])
        ->assertRedirect(route('reviewer.assignments.evaluation', [$this->submission, 'form' => $this->administration->id]));
});

it('[FE-WINDOW-02] simpan draft ditolak setelah tenggat evaluasi lewat', function () {
    $started = evaluationPageStarted($this->assignment, $this->administration, 'draft', now()->subDay());

    $this->actingAs($this->user)
        ->post(route('reviewer.evaluation-form.save-draft', $started), ['responses' => [$this->field->id => '5']])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('reviewer.assignments.evaluation', $this->submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('forms.1.state', 'overdue')
            ->where('forms.1.canEdit', false));
});

it('[FE-WINDOW-01] tenggat yang belum lewat tetap bisa disimpan', function () {
    $started = evaluationPageStarted($this->assignment, $this->administration, 'draft', now()->addDay());

    $this->actingAs($this->user)
        ->post(route('reviewer.evaluation-form.save-draft', $started), ['responses' => [$this->field->id => '5']])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});
