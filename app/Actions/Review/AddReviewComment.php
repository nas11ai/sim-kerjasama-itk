<?php

namespace App\Actions\Review;

use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewCommentAddedNotification;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Queries\Review\DiscussionParticipants;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-06 — satu komentar pada ulir diskusi.
 *
 * @method static ReviewComment run(ReviewSummary $summary, User $author, string $text, ?int $parentId = null)
 */
class AddReviewComment
{
    use AsAction;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(ReviewSummary $summary, User $author, string $text, ?int $parentId = null): ReviewComment
    {
        $reviewerId = $this->authorize($summary, $author);
        $this->guardParent($summary, $parentId);

        $comment = ReviewComment::create([
            'review_summary_id' => $summary->id,
            'parent_comment_id' => $parentId,
            'user_id' => $reviewerId === null ? $author->id : null,
            'reviewer_id' => $reviewerId,
            'comment_text' => $text,
        ]);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Komentar review ditambahkan', $comment, $author);

        // BR-NOT-03 — dikirim sesudah komentarnya tersimpan; penulisnya tak perlu diberi tahu.
        Deliver::to(
            DiscussionParticipants::run($summary->formSubmission, $author->id),
            new ReviewCommentAddedNotification($comment, $summary->form_submission_id),
            ['review_comment_id' => $comment->id],
        );

        return $comment;
    }

    /**
     * Operator dan pengaju selalu boleh; reviewer hanya bila penugasannya aktif dan
     * evaluasinya beres (BR-SM-07, BR-REV-10, BR-REV-06).
     *
     * @return int|null id reviewer bila penulis berkomentar sebagai reviewer
     *
     * @throws AuthorizationException
     */
    private function authorize(ReviewSummary $summary, User $author): ?int
    {
        $submission = $summary->formSubmission;

        if ($author->can('users.manage') || $submission->submitted_by === $author->id) {
            return null;
        }

        $reviewer = Reviewer::where('user_id', $author->id)->first();
        $assignment = $reviewer === null ? null : ActiveReviewerAssignment::run($submission, $reviewer->id);

        if ($reviewer === null || $assignment === null) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengomentari review ini');
        }

        if (!$assignment->hasSettledEvaluation()) {
            throw new AuthorizationException($this->unsettledEvaluationMessage($assignment));
        }

        return $reviewer->id;
    }

    /**
     * "Selesaikan 0 formulir" membingungkan: evaluasi bisa belum beres meski tak ada
     * formulir tertunda, jadi jumlahnya hanya disebut bila memang ada.
     */
    private function unsettledEvaluationMessage(SubmissionReviewer $assignment): string
    {
        $pending = $assignment->pending_forms_count;

        if ($pending === 0) {
            return 'Selesaikan evaluasi Anda sebelum berpartisipasi dalam diskusi.';
        }

        return "Selesaikan {$pending} formulir evaluasi yang tertunda sebelum berpartisipasi dalam diskusi.";
    }

    /**
     * Balasan hanya boleh menempel pada komentar di ulir yang sama.
     *
     * @throws ValidationException
     */
    private function guardParent(ReviewSummary $summary, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $inThread = ReviewComment::query()
            ->where('id', $parentId)
            ->where('review_summary_id', $summary->id)
            ->exists();

        if ($inThread) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_comment_id' => 'Komentar yang dibalas bukan bagian dari ulir ini.',
        ]);
    }
}
