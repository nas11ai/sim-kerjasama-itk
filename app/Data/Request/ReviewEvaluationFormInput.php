<?php

namespace App\Data\Request;

/**
 * Isi layar Buat/Ubah form evaluasi reviewer. Field null berarti kiriman tak menyebut field:
 * tidak ada perubahan field. Wizard form builder memakai EvaluationFormInput, bukan ini.
 */
final class ReviewEvaluationFormInput
{
    /**
     * @param  list<ReviewFormFieldInput>|null  $fields
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $formPhaseDetailId,
        public readonly bool $isRequired,
        public readonly bool $isActive,
        public readonly ?array $fields,
    ) {}

    /** @return array{title: string, description: string|null, form_phase_detail_id: int, is_required: bool, is_active: bool} */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'form_phase_detail_id' => $this->formPhaseDetailId,
            'is_required' => $this->isRequired,
            'is_active' => $this->isActive,
        ];
    }
}
