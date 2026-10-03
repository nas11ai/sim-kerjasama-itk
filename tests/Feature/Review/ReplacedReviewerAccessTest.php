<?php

// tests/Feature/Review/ReplacedReviewerAccessTest.php
//
// BR-SM-07, BR-REV-10 — record reviewer yang sudah diganti disimpan sebagai
// sejarah, bukan sebagai wewenang. Fixture-nya sengaja realistis: evaluasinya
// selesai sebelum diganti, jadi gerbang "evaluasi beres" saja tidak menolaknya.

use App\Mail\DynamicEmailNotification;
use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormResponse;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** @return array{0: User, 1: Reviewer, 2: FormSubmission, 3: SubmissionReviewer} */
function replacedReviewerFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);

    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'replaced',
    ]);

    return [$user, $reviewer, $submission, $assignment];
}

function replacedReviewerOperator(): User
{
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    return $operator;
}

/** Form evaluasi milik penugasan itu, dengan satu field opsional. */
function replacedReviewerFormAssignment(SubmissionReviewer $assignment): ReviewerFormAssignment
{
    $submission = $assignment->formSubmission;

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    ReviewFormField::create([
        'review_evaluation_form_id' => $form->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'label' => 'Catatan',
        'is_required' => false,
        'order' => 1,
    ]);

    return ReviewerFormAssignment::create([
        'submission_reviewer_id' => $assignment->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);
}

/** Ulir milik reviewer lain yang masih aktif. */
function replacedReviewerOpenThread(FormSubmission $submission): ReviewSummary
{
    $other = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $other->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    return ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $other->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);
}

it('menolak reviewer yang sudah diganti membuka ulir baru', function () {
    [$user, , $submission] = replacedReviewerFixture();

    $this->actingAs($user)
        ->post(route('reviewer.review-threads.store', $submission), ['summary_notes' => 'Catatan.'])
        ->assertForbidden();

    expect(ReviewSummary::count())->toBe(0);
});

it('menolak reviewer yang sudah diganti berkomentar di ulir', function () {
    [$user, , $submission] = replacedReviewerFixture();
    $thread = replacedReviewerOpenThread($submission);

    $this->actingAs($user)
        ->post(route('reviewer.review-comments.store', $thread), ['comment_text' => 'Saya setuju.'])
        ->assertForbidden();
});

it('menolak reviewer yang sudah diganti mengubah status ulir', function () {
    [$user, $reviewer, $submission] = replacedReviewerFixture();

    // Ulirnya sendiri yang belum sempat diwariskan (BR-REV-15): yang diuji
    // penugasannya, bukan kepemilikan ulir.
    $thread = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Catatan lama.',
    ]);

    $this->actingAs($user)
        ->patch(route('reviewer.review-summaries.update-status', $thread), ['status' => 'resolved'])
        ->assertForbidden();

    expect($thread->fresh()?->status)->toBe('open');
});

it('menolak menugaskan form evaluasi kepada reviewer yang sudah diganti', function () {
    [, $reviewer, $submission] = replacedReviewerFixture();
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $this->actingAs(replacedReviewerOperator())
        ->post(route('admin.submissions.assign-evaluation-forms', [$submission, $reviewer]), [
            'form_assignments' => [['form_id' => $form->id, 'is_required' => true, 'due_date' => null]],
        ])
        ->assertSessionHasErrors('error');

    expect(ReviewerFormAssignment::count())->toBe(0);
});

it('menolak penugasan massal kepada reviewer yang sudah diganti', function () {
    [, $reviewer, $submission] = replacedReviewerFixture();
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $this->actingAs(replacedReviewerOperator())
        ->post(route('admin.submissions.assign-forms-bulk', $submission), [
            'reviewer_ids' => [$reviewer->id],
            'form_assignments' => [['form_id' => $form->id, 'is_required' => true, 'due_date' => null]],
        ]);

    expect(ReviewerFormAssignment::count())->toBe(0);
});

it('tidak menghapus record reviewer yang sudah diganti — BR-REV-10', function () {
    [, $reviewer, $submission, $assignment] = replacedReviewerFixture();

    // Penggantinya masih bertugas. Tanpa reviewer aktif lain, transisi balik ke
    // SUBMITTED gagal dan me-rollback semuanya — test akan lolos karena kebetulan.
    replacedReviewerOpenThread($submission);

    $this->actingAs(replacedReviewerOperator())
        ->delete(route('admin.submissions.remove-reviewer', [$submission, $reviewer]))
        ->assertNotFound();

    expect(SubmissionReviewer::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('menolak reviewer yang sudah diganti membuka form evaluasi', function () {
    [$user, , , $assignment] = replacedReviewerFixture();
    $formAssignment = replacedReviewerFormAssignment($assignment);

    $this->actingAs($user)
        ->get(route('reviewer.evaluation-form.show', $formAssignment))
        ->assertForbidden();

    // Membuka form lama diam-diam membuat draft respons baru.
    expect(ReviewFormResponse::count())->toBe(0);
});

it('menolak reviewer yang sudah diganti mengirim form evaluasi', function () {
    [$user, , , $assignment] = replacedReviewerFixture();
    $formAssignment = replacedReviewerFormAssignment($assignment);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $formAssignment->id,
        'status' => 'draft',
    ]);
    $field = $formAssignment->reviewEvaluationForm->reviewFormFields()->firstOrFail();

    $this->actingAs($user)
        ->post(route('reviewer.evaluation-form.submit', $formAssignment), [
            'responses' => [$field->id => 'Penilaian setelah diganti.'],
        ])
        ->assertForbidden();

    expect($response->fresh()?->status)->toBe('draft')
        ->and($response->reviewFormFieldResponses()->count())->toBe(0);
});

it('tidak mengirim isi ulir baru ke reviewer yang sudah diganti', function () {
    Mail::fake();
    Role::findOrCreate('Super Admin', 'web');
    Role::findOrCreate('Admin', 'web');

    [$user, , $submission] = replacedReviewerFixture();
    $author = replacedReviewerOpenThread($submission)->reviewer?->user;
    $operator = replacedReviewerOperator()->assignRole('Admin');

    $this->actingAs($author)
        ->post(route('reviewer.review-threads.store', $submission), ['summary_notes' => 'Catatan baru.'])
        ->assertRedirect();

    // Admin tetap menerima — tanpa itu test lolos hanya karena emailnya gagal.
    Mail::assertQueued(DynamicEmailNotification::class, fn ($mail) => $mail->hasTo($operator->email));
    Mail::assertNotQueued(DynamicEmailNotification::class, fn ($mail) => $mail->hasTo($user->email));
});
