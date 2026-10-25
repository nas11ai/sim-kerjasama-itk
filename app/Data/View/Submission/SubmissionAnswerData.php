<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** Satu jawaban field form di layar detail; nilai opsi sudah diganti labelnya. */
final class SubmissionAnswerData extends Data
{
    public function __construct(
        public string $label,
        public ?string $value,
        public bool $isFile,
    ) {}
}
