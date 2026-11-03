<?php

// tests/Feature/Review/EvaluationResponseTest.php
//
// Reviewer mengisi form evaluasi lewat Action beraudit (BR-RPT-09). Hanya reviewer yang
// penugasannya masih berlaku yang boleh menjawab — admin cuma membaca — dan jawabannya
// hanya untuk field milik form itu sendiri.

use App\Enums\DeadlineType;
use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $this->period = SubmissionPeriod::create(['name' => 'Periode Evaluasi '.fake()->unique()->numerify('####')]);
    $this->submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submission_period_id' => $this->period->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);

    $this->reviewerUser = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $this->reviewerUser->id]);
    $this->submissionReviewer = SubmissionReviewer::create([
        'form_submission_id' => $this->submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'not_required',
        'status' => 'active',
    ]);

    $this->form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);
    $this->required = evalResponseField($this->form, 'Kelayakan', true);
    $this->optional = evalResponseField($this->form, 'Saran', false);
});

function evalResponseField(ReviewEvaluationForm $form, string $label, bool $required): ReviewFormField
{
    return ReviewFormField::create([
        'review_evaluation_form_id' => $form->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'label' => $label,
        'is_required' => $required,
        'order' => 1,
    ]);
}

function evalResponseAssignment(SubmissionReviewer $reviewer, ReviewEvaluationForm $form): ReviewerFormAssignment
{
    return ReviewerFormAssignment::create([
        'submission_reviewer_id' => $reviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);
}

function evalResponseAdmin(): User
{
    Permission::findOrCreate('users.manage', 'web');

    return User::factory()->create()->givePermissionTo('users.manage');
}

function evalResponseAudit(Model $subject, string $description): bool
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->where('description', $description)
        ->exists();
}

it('lets the active reviewer start an evaluation form, due on the Batas Evaluasi of its detail (BR-FE-26)', function () {
    // Tanggal terakhir periode (+30, Batas Revisi) milik tahap lain; reviewer memakai Batas Evaluasi.
    $evaluation = SubmissionDate::create(['type' => DeadlineType::Evaluation, 'datetime' => now()->addDays(20), 'submission_period_id' => $this->period->id]);
    SubmissionDate::create(['type' => DeadlineType::Revision, 'datetime' => now()->addDays(30), 'submission_period_id' => $this->period->id]);
    $this->submission->formPhaseDetail->update(['evaluation_date_id' => $evaluation->id]);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.start'), [
            'submission_id' => $this->submission->id,
            'review_evaluation_form_id' => $this->form->id,
        ])
        ->assertRedirect();

    $assignment = ReviewerFormAssignment::sole();

    // BR-REV-05 — form wajib baru berarti ada yang ditunggu lagi dari reviewer ini.
    expect($assignment->submission_reviewer_id)->toBe($this->submissionReviewer->id)
        ->and($assignment->due_date?->toDateString())->toBe(now()->addDays(20)->toDateString())
        ->and($this->submissionReviewer->fresh()?->evaluation_status)->toBe('pending')
        ->and(evalResponseAudit($assignment, 'Form evaluasi dimulai reviewer'))->toBeTrue();
});

it('falls back to a week from now when the submission period has no dates', function () {
    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.start'), [
            'submission_id' => $this->submission->id,
            'review_evaluation_form_id' => $this->form->id,
        ]);

    expect(ReviewerFormAssignment::sole()->due_date?->toDateString())->toBe(now()->addWeek()->toDateString());
});

it('refuses to start a form of another phase detail, an inactive form, or by someone not assigned', function () {
    $foreign = ReviewEvaluationForm::create([
        'title' => 'Lain',
        'form_phase_detail_id' => FormPhaseDetail::factory()->create(['needs_review' => true])->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);
    $inactive = ReviewEvaluationForm::create([
        'title' => 'Nonaktif',
        'form_phase_detail_id' => $this->form->form_phase_detail_id,
        'is_active' => false,
        'is_required' => true,
        'order' => 2,
    ]);
    $start = fn (User $user, ReviewEvaluationForm $form) => $this->actingAs($user)
        ->post(route('reviewer.evaluation-form.start'), [
            'submission_id' => $this->submission->id,
            'review_evaluation_form_id' => $form->id,
        ]);

    $start($this->reviewerUser, $foreign)->assertForbidden();
    // BR-FE-22 — form yang dinonaktifkan berhenti ditawarkan untuk pekerjaan baru.
    $start($this->reviewerUser, $inactive)->assertForbidden();
    $start(User::factory()->create(), $this->form)->assertForbidden();

    expect(ReviewerFormAssignment::count())->toBe(0);
});

it('sends the old form links to the tab of that form on the evaluation page, leaving no draft behind', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);
    $page = route('reviewer.assignments.evaluation', ['submission' => $this->submission->id, 'form' => $this->form->id]);

    foreach (['reviewer.evaluation-form.show', 'reviewer.evaluation-form.submitted'] as $name) {
        $this->actingAs($this->reviewerUser)->get(route($name, $assignment))->assertRedirect($page);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('reviewer.evaluation-form.show', $assignment))
        ->assertForbidden();

    // Draft baru dibuat saat reviewer menyimpan (REV-EVAL-01), bukan saat membuka.
    expect(ReviewFormResponse::count())->toBe(0);
});

it('[REV-EVAL-01] saves a draft of the answers and the final notes, audited', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $assignment), [
            'responses' => [$this->required->id => 'Layak'],
            'final_notes' => 'Catatan akhir',
        ])
        ->assertSessionHasNoErrors();

    $response = ReviewFormResponse::sole();

    expect($response->final_notes)->toBe('Catatan akhir')
        ->and($response->reviewFormFieldResponses()->sole()->value)->toBe('Layak')
        ->and(evalResponseAudit($response, 'Draft evaluasi disimpan'))->toBeTrue();
});

it('rejects an answer for a field of another evaluation form, saving nothing', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);
    $other = ReviewEvaluationForm::create([
        'title' => 'Form lain',
        'form_phase_detail_id' => $this->form->form_phase_detail_id,
        'is_active' => true,
        'is_required' => false,
        'order' => 2,
    ]);
    $foreignField = evalResponseField($other, 'Milik form lain', false);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $assignment), [
            'responses' => [$this->required->id => 'Layak', $foreignField->id => 'Bajakan'],
        ])
        ->assertSessionHasErrors('responses');

    expect($foreignField->reviewFormFieldResponses()->count())->toBe(0)
        ->and($this->required->reviewFormFieldResponses()->count())->toBe(0);
});

it('[REV-EVAL-02] refuses to save a draft over a submitted evaluation', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);
    $response = ReviewFormResponse::create(['reviewer_form_assignment_id' => $assignment->id, 'status' => 'submitted']);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $assignment), [
            'responses' => [$this->required->id => 'Diubah setelah submit'],
        ])
        ->assertSessionHasErrors('error');

    expect($response->reviewFormFieldResponses()->count())->toBe(0);
});

it('[REV-EVAL-03] submits fresh answers in one request, completing the assignment', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);

    // Layar mengaktifkan tombol kirim dari isian di peramban; draft belum tentu tersimpan dulu.
    $page = route('reviewer.assignments.evaluation', $this->submission);

    $this->actingAs($this->reviewerUser)
        ->from($page)
        ->post(route('reviewer.evaluation-form.submit', $assignment), [
            'responses' => [$this->required->id => 'Layak'],
        ])
        ->assertRedirect($page);

    $response = ReviewFormResponse::sole();

    expect($response->status)->toBe('submitted')
        ->and($this->submissionReviewer->fresh()?->evaluation_status)->toBe('completed')
        ->and(evalResponseAudit($response, 'Evaluasi disubmit'))->toBeTrue();
});

it('rejects a submission missing a required answer and keeps nothing of it', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);
    ReviewFormResponse::create(['reviewer_form_assignment_id' => $assignment->id, 'status' => 'draft']);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.submit', $assignment), [
            'responses' => [$this->required->id => '', $this->optional->id => 'Saran'],
        ])
        ->assertSessionHasErrors('responses');

    $response = ReviewFormResponse::sole();

    expect($response->status)->toBe('draft')
        ->and($response->reviewFormFieldResponses()->count())->toBe(0);
});

it('lets neither an admin nor a super admin answer on behalf of the reviewer', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);
    Role::findOrCreate('Super Admin', 'web');
    $superAdmin = User::factory()->create()->assignRole('Super Admin');

    foreach ([evalResponseAdmin(), $superAdmin] as $user) {
        $this->actingAs($user)
            ->post(route('reviewer.evaluation-form.save-draft', $assignment), ['responses' => [$this->required->id => 'Oleh admin']])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('reviewer.evaluation-form.submit', $assignment), ['responses' => [$this->required->id => 'Oleh admin']])
            ->assertForbidden();
    }

    expect(ReviewFormResponse::count())->toBe(0);
});

it('downloads a submitted evaluation and reports its progress', function () {
    $assignment = evalResponseAssignment($this->submissionReviewer, $this->form);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'submitted',
        'submitted_at' => now(),
        'final_notes' => 'Catatan penutup',
    ]);
    $response->saveFieldResponse($this->required->id, 'Layak');

    $download = $this->actingAs($this->reviewerUser)
        ->get(route('reviewer.evaluation-form.download-summary', $assignment))
        ->assertOk();

    expect($download->streamedContent())
        ->toContain("Kelayakan\nResponse: Layak")
        ->toContain("Saran\nResponse: [No response]")
        ->toContain("Catatan Tambahan:\nCatatan penutup");

    $this->actingAs($this->reviewerUser)
        ->getJson(route('reviewer.evaluation-form.progress', $assignment))
        ->assertOk()
        ->assertJson(['status' => 'submitted', 'completion_percentage' => 50, 'can_edit' => false]);
});
