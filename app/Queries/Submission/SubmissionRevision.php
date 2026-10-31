<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Data\View\Submission\Revision\ReviewAttachmentData;
use App\Data\View\Submission\Revision\ReviewCommentData;
use App\Data\View\Submission\Revision\ReviewThreadData;
use App\Data\View\Submission\Revision\RevisionSubmissionData;
use App\Data\View\Submission\Wizard\WizardMissingFieldData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\ReviewCommentAttachment;
use App\Models\ReviewSummary;
use App\Models\ReviewSummaryAttachment;
use App\Models\User;
use App\Queries\Review\ReviewThreads;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * RESEARCHER-02-05 — ulir reviewer dan penghalang kirim ulang. Akses (BR-SM-07) dan status
 * NEEDS_REVISION dijaga controller sebelum Query ini dipanggil.
 *
 * @method static array{submission: RevisionSubmissionData, threads: list<ReviewThreadData>, missingFields: list<WizardMissingFieldData>, accountBlock: string|null, can: SubmissionAbilitiesData} run(User $viewer, FormSubmission $submission)
 */
class SubmissionRevision implements ChangesNoState
{
    use AsAction;

    /** @return array{submission: RevisionSubmissionData, threads: list<ReviewThreadData>, missingFields: list<WizardMissingFieldData>, accountBlock: string|null, can: SubmissionAbilitiesData} */
    public function handle(User $viewer, FormSubmission $submission): array
    {
        $submission->loadMissing('form:id,title');
        $threads = ReviewThreads::run($submission);
        // BR-SM-02, SM-SUBMIT-19 — penghalang yang sama dengan yang diperiksa ResubmitFormSubmission.
        $review = WizardReviewSummary::run($viewer, $submission);
        $comments = $threads['comments']->groupBy('review_summary_id');

        return [
            'submission' => new RevisionSubmissionData($submission->id, $submission->form->title, SubmissionStatusData::of($submission)),
            'threads' => array_values($threads['summaries']->map(
                fn (ReviewSummary $summary): ReviewThreadData => $this->thread($summary, $comments->get($summary->id, new Collection))
            )->all()),
            'missingFields' => $review->missingFields,
            'accountBlock' => $review->accountBlock,
            'can' => SubmissionAbilitiesData::forUser($viewer, $submission),
        ];
    }

    /** @param  Collection<int, ReviewComment>  $comments  terbaru dulu, dari ReviewThreads */
    private function thread(ReviewSummary $summary, Collection $comments): ReviewThreadData
    {
        return new ReviewThreadData(
            id: $summary->id,
            // BR-REV-15 — penulis asli, meski penugasannya sudah diganti.
            reviewer: $summary->reviewer?->user->name ?? 'Reviewer',
            isOpen: $summary->status === 'open',
            notes: $summary->summary_notes,
            at: $summary->created_at?->toIso8601String(),
            attachments: array_values($summary->attachments->map(
                fn (ReviewSummaryAttachment $file): ReviewAttachmentData => ReviewAttachmentData::fromPath($file->file_path)
            )->all()),
            comments: array_values($comments->reverse()->map($this->comment(...))->all()),
        );
    }

    private function comment(ReviewComment $comment): ReviewCommentData
    {
        return new ReviewCommentData(
            id: $comment->id,
            author: $comment->reviewer_id !== null ? ($comment->reviewer?->user->name ?? 'Reviewer') : ($comment->user->name ?? '—'),
            body: $comment->comment_text,
            at: $comment->created_at?->toIso8601String(),
            replies: array_values($comment->replies->map($this->comment(...))->all()),
            attachments: array_values($comment->attachments->map(
                fn (ReviewCommentAttachment $file): ReviewAttachmentData => ReviewAttachmentData::fromPath($file->file_path)
            )->all()),
        );
    }
}
