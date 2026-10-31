<?php

namespace App\Data\View\Submission\Wizard;

use App\Data\View\SubmissionStatusData;
use Spatie\LaravelData\Data;

/** Kepala wizard. `lastUpdatedAt` dikirim balik sebagai cap optimistic lock (BR-FE-14). */
final class WizardSubmissionData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public SubmissionStatusData $status,
        public ?string $period,
        public string $form,
        public string $lastUpdatedAt,
    ) {}
}
