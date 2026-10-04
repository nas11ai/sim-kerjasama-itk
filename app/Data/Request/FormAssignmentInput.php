<?php

namespace App\Data\Request;

use Carbon\CarbonInterface;

/** Satu form evaluasi yang hendak ditugaskan, beserta syaratnya. */
final class FormAssignmentInput
{
    public function __construct(
        public readonly int $formId,
        public readonly bool $isRequired,
        public readonly ?CarbonInterface $dueDate,
    ) {}
}
