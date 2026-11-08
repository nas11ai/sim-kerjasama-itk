<?php

namespace App\Data\View\FormEngine;

use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use Spatie\LaravelData\Data;

/**
 * Bagian form evaluasi satu detail ber-`needs_review` (ubiquitous language: form evaluasi
 * attached ke detail yang direview). Label "{phaseTypeName} — {formTitle}" seperti baris detail (W93).
 */
final class EvaluationSectionData extends Data
{
    /** @param  list<EvaluationFormEditData>  $forms  urut `order` */
    public function __construct(
        public int $detailId,
        public string $detailLabel,
        public array $forms,
        // W158 — pengajuan ber-reviewer aktif yang tak otomatis menerima form baru.
        public int $reviewsInProgress,
    ) {}

    public static function fromModel(FormPhaseDetail $detail, int $reviewsInProgress): self
    {
        return new self(
            detailId: $detail->id,
            detailLabel: ($detail->phaseType->name ?? '').' — '.($detail->formAccessControl->form->title ?? ''),
            forms: array_values($detail->reviewEvaluationForms
                ->map(fn (ReviewEvaluationForm $form): EvaluationFormEditData => EvaluationFormEditData::fromModel($form))
                ->all()),
            reviewsInProgress: $reviewsInProgress,
        );
    }
}
