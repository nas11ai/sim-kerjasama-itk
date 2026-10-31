<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** Form yang bisa dimulai di langkah 1; `draftId` menaut draft yang masih bisa disunting. */
final class StartFormOptionData extends Data
{
    public function __construct(
        public int $formId,
        public int $formPhaseId,
        public string $title,
        public ?int $draftId,
    ) {}
}
