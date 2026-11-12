<?php

namespace App\Data\View\FormEngine;

use App\Models\FormPhaseDetail;
use App\Models\StepEvaluationForm;
use Spatie\LaravelData\Data;

/**
 * Bagian form evaluasi satu detail ber-`needs_review`: Form Evaluasi pustaka yang terpasang di
 * sana (BR-FE-30). Label "{phaseTypeName} — {formTitle}" seperti baris detail (W93).
 */
final class EvaluationSectionData extends Data
{
    /** @param  list<StepEvaluationFormData>  $attached  urut `order` */
    public function __construct(
        public int $detailId,
        public string $detailLabel,
        public array $attached,
        // W158 — pengajuan ber-reviewer aktif yang tak otomatis menerima form baru.
        public int $reviewsInProgress,
    ) {}

    public static function fromModel(FormPhaseDetail $detail, int $reviewsInProgress): self
    {
        return new self(
            detailId: $detail->id,
            detailLabel: ($detail->phaseType->name ?? '').' — '.($detail->formAccessControl->form->title ?? ''),
            attached: array_values($detail->stepEvaluationForms
                ->map(fn (StepEvaluationForm $step): StepEvaluationFormData => StepEvaluationFormData::fromModel($step))
                ->all()),
            reviewsInProgress: $reviewsInProgress,
        );
    }
}
