<?php

// tests/Feature/Review/BulkFormAssignmentTest.php
//
// BR-RPT-09 — satu peristiwa domain, satu jejak audit, lewat jalur mana pun.
// Penugasan massal dulu membuat ReviewerFormAssignment langsung dari controller
// sehingga tidak meninggalkan jejak apa pun, sementara jalur ReviewController
// yang sama-sama menugaskan form mencatatnya.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: FormSubmission, 2: Reviewer, 3: ReviewEvaluationForm} */
function bulkAssignFixture(): array
{
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);

    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'not_required',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return [$operator, $submission, $reviewer, $form];
}

function bulkAssign(User $operator, FormSubmission $submission, Reviewer $reviewer, ReviewEvaluationForm $form): void
{
    test()->actingAs($operator)
        ->post(route('admin.submissions.assign-forms-bulk', $submission), [
            'reviewer_ids' => [$reviewer->id],
            'form_assignments' => [
                ['form_id' => $form->id, 'is_required' => true, 'due_date' => null],
            ],
        ])
        ->assertSessionHasNoErrors();
}

it('mencatat jejak audit untuk penugasan massal — BR-RPT-09', function () {
    [$operator, $submission, $reviewer, $form] = bulkAssignFixture();

    bulkAssign($operator, $submission, $reviewer, $form);

    $entries = Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', (new ReviewerFormAssignment)->getMorphClass())
        ->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()?->description)->toBe('Form evaluasi ditugaskan');
});

it('menghitung ulang status evaluasi sesudah penugasan massal', function () {
    [$operator, $submission, $reviewer, $form] = bulkAssignFixture();

    bulkAssign($operator, $submission, $reviewer, $form);

    // Form wajib baru masuk, jadi reviewer yang tadinya not_required kembali
    // punya pekerjaan.
    expect(SubmissionReviewer::where('reviewer_id', $reviewer->id)->first()?->evaluation_status)
        ->toBe('pending');
});

it('menugaskan ulang lewat jalur massal tanpa menggandakan baris', function () {
    [$operator, $submission, $reviewer, $form] = bulkAssignFixture();

    bulkAssign($operator, $submission, $reviewer, $form);
    bulkAssign($operator, $submission, $reviewer, $form);

    expect(ReviewerFormAssignment::count())->toBe(1);
});

it('menugaskan reviewer yang bertugas dan menyebut yang dilewati dalam bahasa Indonesia — BR-SM-07', function () {
    [$operator, $submission, $reviewer, $form] = bulkAssignFixture();
    $outsider = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    test()->actingAs($operator)
        ->post(route('admin.submissions.assign-forms-bulk', $submission), [
            'reviewer_ids' => [$reviewer->id, $outsider->id],
            'form_assignments' => [
                ['form_id' => $form->id, 'is_required' => true, 'due_date' => null],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Berhasil menugaskan form kepada 1 reviewer')
            && str_contains($message, "ID {$outsider->id}")
            && str_contains($message, 'tidak ditugaskan pada pengajuan ini'));

    expect(ReviewerFormAssignment::count())->toBe(1);
});
