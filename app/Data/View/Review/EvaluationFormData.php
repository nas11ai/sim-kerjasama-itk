<?php

namespace App\Data\View\Review;

use App\Data\View\Submission\Wizard\WizardFieldData;
use Spatie\LaravelData\Data;

/**
 * Satu form evaluasi di REVIEWER-03-03. `assignmentId` null = belum dimulai, jadi belum ada
 * tempat menyimpan jawaban. `state` hanya memilih tampilan; boleh-tidaknya menyunting di `canEdit`.
 */
final class EvaluationFormData extends Data
{
    /** @param  list<WizardFieldData>  $fields */
    public function __construct(
        public int $formId,
        public ?int $assignmentId,
        public string $title,
        public ?string $description,
        public bool $required,
        public string $state,
        public ?string $dueDate,
        public ?string $submittedAt,
        public ?string $finalNotes,
        public bool $canEdit,
        public array $fields,
        // BR-REV-18 — versi yang diisi reviewer, mis. "Versi 2".
        public string $versionLabel,
    ) {}
}
