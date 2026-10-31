<?php

namespace App\Data\View\Submission\Revision;

use Spatie\LaravelData\Data;

/** Lampiran ulir atau komentar; `url` lewat route unduh ber-scope BR-SM-07. */
final class ReviewAttachmentData extends Data
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}

    public static function fromPath(string $path): self
    {
        return new self(basename($path), route('review-attachments.download', ['path' => $path]));
    }
}
