<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** BR-SM-17 — satu langkah di Stepper; langkah terkunci tetap bisa dibuka untuk membaca alasannya. */
final class WizardStepData extends Data
{
    public function __construct(
        public int $number,
        public string $label,
        public bool $completed,
        public ?string $blockedReason,
    ) {}
}
