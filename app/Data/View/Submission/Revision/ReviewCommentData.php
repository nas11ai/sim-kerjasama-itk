<?php

namespace App\Data\View\Submission\Revision;

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
}
