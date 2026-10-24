<?php

namespace App\Data\Request;

/** Jawaban reviewer atas satu form evaluasi, per id field, beserta catatan akhirnya. */
final class EvaluationAnswersInput
{
    /**
     * @param  array<int, string|null>  $answers
     */
    public function __construct(
        public readonly array $answers,
        public readonly ?string $finalNotes,
    ) {}
}
