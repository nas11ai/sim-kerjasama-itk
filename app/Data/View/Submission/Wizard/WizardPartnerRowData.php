<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** BR-SM-21 — mitra eksternal pengajuan. */
final class WizardPartnerRowData extends Data
{
    public function __construct(
        public int $id,
        public string $institutionName,
        public ?string $contactPerson,
    ) {}
}
