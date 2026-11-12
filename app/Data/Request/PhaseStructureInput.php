<?php

namespace App\Data\Request;

use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\StepEvaluationForm;
use LogicException;

/** BR-FE-32 — isi satu versi struktur tahap: langkah urut seperti di layar, beserta pemasangannya. */
final class PhaseStructureInput
{
    /** @param  list<FormPhaseDetailInput>  $details */
    public function __construct(public readonly array $details) {}

    /** Struktur `$version` apa adanya; `id` langkah ikut supaya pemanggil bisa mengubah satu langkah. */
    public static function fromVersion(FormPhaseVersion $version): self
    {
        return new self(array_values($version->formPhaseDetails()->with('stepEvaluationForms')->get()
            ->map(fn (FormPhaseDetail $detail): FormPhaseDetailInput => new FormPhaseDetailInput(
                id: $detail->id,
                formAccessControlId: $detail->form_access_control_id,
                phaseTypeId: $detail->phase_type_id,
                needsReview: $detail->needs_review,
                deadlineType: $detail->deadline_type,
                deadlineCycle: $detail->deadline_cycle,
                evaluationForms: self::evaluationFormsOf($detail),
            ))->all()));
    }

    /** Posisi langkah ber-`id` itu; versi baru menyalin langkah dengan urutan yang sama. */
    public function indexOf(int $detailId): int
    {
        foreach ($this->details as $index => $detail) {
            if ($detail->id === $detailId) {
                return $index;
            }
        }

        throw new LogicException("Langkah #{$detailId} bukan bagian struktur ini.");
    }

    /** @param  list<StepEvaluationFormInput>  $evaluationForms */
    public function withEvaluationFormsAt(int $index, array $evaluationForms): self
    {
        return new self(array_map(
            fn (FormPhaseDetailInput $detail, int $at): FormPhaseDetailInput => $at === $index ? $detail->withEvaluationForms($evaluationForms) : $detail,
            $this->details,
            array_keys($this->details),
        ));
    }

    /** @return list<StepEvaluationFormInput> */
    public static function evaluationFormsOf(FormPhaseDetail $detail): array
    {
        return array_values($detail->stepEvaluationForms->sortBy([['order', 'asc'], ['id', 'asc']])
            ->map(fn (StepEvaluationForm $step): StepEvaluationFormInput => new StepEvaluationFormInput(
                $step->review_evaluation_form_id,
                $step->is_required,
                $step->is_active,
            ))->all());
    }
}
