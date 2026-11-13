<?php

namespace App\Data\View\Submission\Revision;

use App\Models\ReviewComment;
use App\Models\ReviewSummary;
use App\Models\ReviewSummaryAttachment;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

/**
 * Satu ulir reviewer. `reviewer` adalah penulis asli ulir; `inheritedBy` adalah pemegangnya
 * sekarang bila ulir diwarisi reviewer pengganti (BR-REV-15), selain itu null.
 */
final class ReviewThreadData extends Data
{
    /**
     * @param  list<ReviewAttachmentData>  $attachments
     * @param  list<ReviewCommentData>  $comments
     */
    public function __construct(
        public int $id,
        public string $reviewer,
        public ?string $inheritedBy,
        public bool $isOpen,
        public ?string $notes,
        public ?string $at,
        public array $attachments,
        public array $comments,
    ) {}

    /**
     * @param  Collection<int, ReviewSummary>  $summaries
     * @param  Collection<int, ReviewComment>  $comments  komentar teratas semua ulir, dari ReviewThreads
     * @return list<self>
     */
    public static function many(Collection $summaries, Collection $comments): array
    {
        $byThread = $comments->groupBy('review_summary_id');

        return array_values($summaries->map(
            fn (ReviewSummary $summary): self => self::of($summary, $byThread->get($summary->id, new Collection))
        )->all());
    }

    /** @param  Collection<int, ReviewComment>  $comments  terbaru dulu, dari ReviewThreads */
    public static function of(ReviewSummary $summary, Collection $comments): self
    {
        return new self(
            id: $summary->id,
            // BR-REV-15 — penulis asli tetap; pemegang yang berhak me-resolve bisa berpindah.
            reviewer: $summary->author->user->name ?? 'Penilai',
            inheritedBy: $summary->reviewer_id === $summary->author_reviewer_id
                ? null
                : ($summary->reviewer?->user->name ?? 'Penilai'),
            isOpen: $summary->status === 'open',
            notes: $summary->summary_notes,
            at: $summary->created_at?->toIso8601String(),
            attachments: array_values($summary->attachments->map(
                fn (ReviewSummaryAttachment $file): ReviewAttachmentData => ReviewAttachmentData::fromPath($file->file_path)
            )->all()),
            comments: array_values($comments->reverse()->map(ReviewCommentData::of(...))->all()),
        );
    }
}
