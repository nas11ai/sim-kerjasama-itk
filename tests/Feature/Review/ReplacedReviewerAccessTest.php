<?php

// tests/Feature/Review/ReplacedReviewerAccessTest.php
//
// BR-SM-07, BR-REV-10 — record reviewer yang sudah diganti disimpan sebagai
// sejarah, bukan sebagai wewenang. Fixture-nya sengaja realistis: evaluasinya
// selesai sebelum diganti, jadi gerbang "evaluasi beres" saja tidak menolaknya.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

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
