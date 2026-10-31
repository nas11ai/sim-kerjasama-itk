<?php

namespace App\Data\Request;

/** Satu perpindahan langkah wizard: jawaban langkah yang ditinggalkan dan langkah tujuannya. */
final class WizardStepInput
{
    public function __construct(
        public readonly SubmissionAnswersInput $answers,
        public readonly int $step,
        public readonly int $goto,
    ) {}
}
