<?php

namespace App\Data\View\FormEngine;

use App\Models\StepEvaluationForm;
use Spatie\LaravelData\Data;

/**
 * Satu pemasangan Form Evaluasi di bagian evaluasi editor tahap (BR-FE-30). `assignmentCount`
 * hanya menghitung penugasan pada pengajuan di langkah ini: > 0 berarti lepas ditolak, hanya
 * bisa dinonaktifkan (BR-FE-22).
 */
final class StepEvaluationFormData extends Data
{
    public function __construct(
        public int $id,
        public int $formId,
        public string $title,
        public string $versionLabel,
        public bool $isRequired,
        public bool $isActive,
        public int $assignmentCount,
    ) {}

    // Dimuat PhaseEvaluationForms: form beserta versi terbarunya, `assignment_count` lewat subquery.
    public static function fromModel(StepEvaluationForm $step): self
    {
        $form = $step->reviewEvaluationForm;

        return new self(
            id: $step->id,
            formId: $form->id,
            title: $form->title,
            // BR-FE-30 — yang terpasang selalu sudah terbit, jadi versi terbarunya ada.
            versionLabel: $form->latestVersion?->label() ?? '',
            isRequired: $step->is_required,
            isActive: $step->is_active,
            assignmentCount: $step->assignment_count ?? 0,
        );
    }
}
