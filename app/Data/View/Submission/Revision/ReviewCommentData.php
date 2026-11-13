<?php

namespace App\Data\View\Submission\Revision;

use App\Models\ReviewComment;
use App\Models\ReviewCommentAttachment;
use Spatie\LaravelData\Data;

/** Satu komentar ulir beserta balasannya, terlama dulu. */
final class ReviewCommentData extends Data
{
    /**
     * @param  list<ReviewCommentData>  $replies
     * @param  list<ReviewAttachmentData>  $attachments
     */
    public function __construct(
        public int $id,
        public string $author,
        public string $body,
        public ?string $at,
        public array $replies,
        public array $attachments,
    ) {}

    /** Relasi `user`, `reviewer.user`, `attachments`, dan `replies` dimuat `ReviewThreads`. */
    public static function of(ReviewComment $comment): self
    {
        return new self(
            id: $comment->id,
            author: $comment->reviewer_id !== null ? ($comment->reviewer?->user->name ?? 'Penilai') : ($comment->user->name ?? '-'),
            body: $comment->comment_text,
            at: $comment->created_at?->toIso8601String(),
            replies: array_values($comment->replies->map(self::of(...))->all()),
            attachments: array_values($comment->attachments->map(
                fn (ReviewCommentAttachment $file): ReviewAttachmentData => ReviewAttachmentData::fromPath($file->file_path)
            )->all()),
        );
    }
}
