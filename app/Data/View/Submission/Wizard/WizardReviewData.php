<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** Ringkasan langkah 7: isi pengajuan dan apa yang masih menghalangi submit. */
final class WizardReviewData extends Data
{
    /**
     * @param  list<string>  $fileNames
     * @param  list<WizardMissingFieldData>  $missingFields
     */
    public function __construct(
        public ?string $scheme,
        public ?string $trl,
        public int $memberCount,
        public int $targetOutputCount,
        public int $budgetTotal,
        public ?int $maxBudget,
        public array $fileNames,
        public array $missingFields,
        public ?string $accountBlock,
    ) {}
}
