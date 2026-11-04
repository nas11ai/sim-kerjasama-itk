<?php

namespace App\Data\View\FormEngine;

use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
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

    public static function fromModel(ReviewEvaluationForm $form): self
    {
        return new self(
            id: $form->id,
            title: $form->title,
            description: $form->description,
            isRequired: $form->is_required,
            isActive: $form->is_active,
            assignmentCount: $form->reviewer_form_assignments_count ?? 0,
            fields: array_values($form->reviewFormFields
                ->map(fn (ReviewFormField $field): EvaluationFieldEditData => EvaluationFieldEditData::fromModel($field))
                ->all()),
        );
    }
}
