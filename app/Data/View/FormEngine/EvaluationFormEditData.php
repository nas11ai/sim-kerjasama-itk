<?php

namespace App\Data\View\FormEngine;

use App\Models\ReviewFormField;
use App\Models\StepEvaluationForm;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

/**
 * Satu kartu form evaluasi di layar sunting tahap (W95). `assignmentCount` dari backend
 * menentukan hapus atau nonaktifkan (BR-FE-22).
 */
final class EvaluationFormEditData extends Data
{
    /** @param  list<EvaluationFieldEditData>  $fields  urut `order` */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public bool $isRequired,
        public bool $isActive,
        public int $assignmentCount,
        public array $fields,
    ) {}

    // Dimuat PhaseEvaluationForms: form beserta field versi terbit terakhirnya (G2-W4, kartu sementara).
    public static function fromModel(StepEvaluationForm $step): self
    {
        $form = $step->reviewEvaluationForm;

        return new self(
            id: $form->id,
            title: $form->title,
            description: $form->description,
            isRequired: $step->is_required,
            isActive: $step->is_active,
            assignmentCount: $form->reviewer_form_assignments_count ?? 0,
            fields: array_values(($form->latestVersion->reviewFormFields ?? new Collection)
                ->map(fn (ReviewFormField $field): EvaluationFieldEditData => EvaluationFieldEditData::fromModel($field))
                ->all()),
        );
    }
}
