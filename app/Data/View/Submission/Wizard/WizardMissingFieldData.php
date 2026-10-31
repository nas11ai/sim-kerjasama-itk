<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** [SM-WIZARD-06] — field wajib yang masih kosong, dengan langkah tempat mengisinya. */
final class WizardMissingFieldData extends Data
{
    public function __construct(
        public int $fieldId,
        public string $label,
        public string $message,
        public int $step,
    ) {}
}
