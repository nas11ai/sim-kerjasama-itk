<?php

namespace App\Data\View\Submission\Revision;

use Spatie\LaravelData\Data;

/**
 * Satu ulir reviewer untuk peneliti. `reviewer` adalah penulis asli ulir, bukan reviewer aktif:
 * ulir tetap milik penulisnya meski ia sudah diganti (BR-REV-15).
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
        public bool $isOpen,
        public ?string $notes,
        public ?string $at,
        public array $attachments,
        public array $comments,
    ) {}
}
