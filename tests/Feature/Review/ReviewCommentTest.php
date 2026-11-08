<?php

// tests/Feature/Review/ReviewCommentTest.php
//
// BR-REV-06 — komentar pada ulir diskusi: siapa boleh (K3), induk harus satu ulir, dan tercatat di audit.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: User, 2: FormSubmission, 3: Reviewer, 4: ReviewSummary} */
function commentFixture(string $evaluation = 'completed'): array
{
    $owner = User::factory()->create();
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);

    $reviewerUser = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $reviewerUser->id]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);

    $thread = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);

    return [$owner, $reviewerUser, $submission, $reviewer, $thread];
}

it('[REV-DISCUSS-02] pengaju membalas ulir open', function () {
    [$owner, , , , $thread] = commentFixture();

    $this->actingAs($owner)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Sudah saya perbaiki.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $comment = ReviewComment::firstOrFail();
    expect($comment->user_id)->toBe($owner->id)
        ->and($comment->reviewer_id)->toBeNull()
        ->and($comment->review_summary_id)->toBe($thread->id);
});

it('reviewer aktif yang evaluasinya beres berkomentar atas nama reviewer', function () {
    [, $reviewerUser, , $reviewer, $thread] = commentFixture();

    $this->actingAs($reviewerUser)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Terima kasih.'])
        ->assertRedirect();

    $comment = ReviewComment::firstOrFail();
    expect($comment->reviewer_id)->toBe($reviewer->id)
        ->and($comment->user_id)->toBeNull();
});

it('menolak reviewer aktif yang evaluasinya belum beres tanpa formulir tertunda', function () {
    [, $reviewerUser, , , $thread] = commentFixture('pending');

    $this->actingAs($reviewerUser)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Terlalu dini.'])
        ->assertForbidden()
        ->assertSee('Selesaikan evaluasi Anda sebelum berpartisipasi dalam diskusi.', false)
        ->assertDontSee('Selesaikan 0 formulir', false);

    expect(ReviewComment::count())->toBe(0);
});

it('menyebut jumlah formulir evaluasi yang masih tertunda', function () {
    [, $reviewerUser, $submission, $reviewer, $thread] = commentFixture('pending');
    $assignment = SubmissionReviewer::query()
        ->where('form_submission_id', $submission->id)
        ->where('reviewer_id', $reviewer->id)
        ->sole();

    foreach (['Substansi', 'Metodologi'] as $order => $title) {
        $form = ReviewEvaluationForm::create([
            'title' => $title,
            'form_phase_detail_id' => $submission->form_phase_detail_id,
            'is_active' => true,
            'is_required' => true,
            'order' => $order + 1,
        ]);
        ReviewerFormAssignment::create([
            'submission_reviewer_id' => $assignment->id,
            'review_evaluation_form_id' => $form->id,
            'is_required' => true,
            'assigned_at' => now(),
            'is_active' => true,
        ]);
    }

    $this->actingAs($reviewerUser)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Terlalu dini.'])
        ->assertForbidden()
        ->assertSee('Selesaikan 2 formulir evaluasi yang tertunda', false);
});

it('menolak balasan yang induknya milik ulir lain', function () {
    [$owner, , $submission, $reviewer, $thread] = commentFixture();

    $other = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Ulir lain.',
    ]);
    $foreign = ReviewComment::create([
        'review_summary_id' => $other->id,
        'reviewer_id' => $reviewer->id,
        'comment_text' => 'Di ulir lain.',
    ]);

    $this->actingAs($owner)
        ->post(route('review-comments.store', $thread), [
            'comment_text' => 'Salah ulir.',
            'parent_comment_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('parent_comment_id');

    expect(ReviewComment::count())->toBe(1);
});

it('mencatat komentar di audit', function () {
    [$owner, , , , $thread] = commentFixture();

    $this->actingAs($owner)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Tercatat.']);

    $comment = ReviewComment::firstOrFail();

    expect(Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $comment->getMorphClass())
        ->where('subject_id', $comment->id)
        ->where('description', 'Komentar review ditambahkan')
        ->exists())->toBeTrue();
});

it('menolak reviewer yang hanya ditugaskan di pengajuan lain', function () {
    [, , , , $thread] = commentFixture();

    $stranger = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $stranger->id]);
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $other = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);
    SubmissionReviewer::create([
        'form_submission_id' => $other->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    $this->actingAs($stranger)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Bukan urusan saya.'])
        ->assertForbidden()
        ->assertSee('Anda tidak memiliki izin untuk mengomentari review ini', false);

    expect(ReviewComment::count())->toBe(0);
});

it('Operator berkomentar atas namanya sendiri', function () {
    [, , , , $thread] = commentFixture();

    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $this->actingAs($operator)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Mohon dipercepat.'])
        ->assertRedirect();

    $comment = ReviewComment::firstOrFail();
    expect($comment->user_id)->toBe($operator->id)
        ->and($comment->reviewer_id)->toBeNull();
});

it('menolak reviewer yang penugasannya sudah diganti', function () {
    [, $reviewerUser, , , $thread] = commentFixture();

    SubmissionReviewer::query()->update(['status' => 'replaced']);

    $this->actingAs($reviewerUser)
        ->post(route('review-comments.store', $thread), ['comment_text' => 'Masih ikut.'])
        ->assertForbidden();

    expect(ReviewComment::count())->toBe(0);
});
