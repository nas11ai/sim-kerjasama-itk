<?php

namespace App\Data\Request;

use App\Enums\DeadlineType;

/**
 * BR-FE-33 — isi draf struktur tahap: langkah urut seperti di layar beserta Formulir Evaluasi
 * terpasangnya. Tanpa id langkah: Terbitkan menyalin baris baru (BR-FE-32), jadi id lintas versi
 * tidak bermakna. Payload hanya ditulis toPayload(), jadi bentuknya pasti.
 *
 * @phpstan-type AttachmentPayload array{review_evaluation_form_id: int, is_required: bool, is_active: bool}
 * @phpstan-type StepPayload array{form_access_control_id: int, phase_type_id: int, needs_review: bool, deadline_type: string, deadline_cycle: int|null, evaluation_forms: list<AttachmentPayload>}
 * @phpstan-type PhaseDraftPayload array{steps: list<StepPayload>}
 */
final class PhaseDraftInput
{
    /** @param  list<FormPhaseDetailInput>  $steps */
    public function __construct(public readonly array $steps) {}

    public function toStructure(): PhaseStructureInput
    {
        return new PhaseStructureInput($this->steps);
    }

    public static function fromStructure(PhaseStructureInput $structure): self
    {
        return new self(array_map(
            fn (FormPhaseDetailInput $step): FormPhaseDetailInput => new FormPhaseDetailInput(
                id: null,
                formAccessControlId: $step->formAccessControlId,
                phaseTypeId: $step->phaseTypeId,
                needsReview: $step->needsReview,
                deadlineType: $step->deadlineType,
                deadlineCycle: $step->deadlineCycle,
                evaluationForms: $step->evaluationForms,
            ),
            $structure->details,
        ));
    }

    /** @return PhaseDraftPayload */
    public function toPayload(): array
    {
        return ['steps' => array_map(fn (FormPhaseDetailInput $step): array => [
            'form_access_control_id' => $step->formAccessControlId,
            'phase_type_id' => $step->phaseTypeId,
            'needs_review' => $step->needsReview,
            'deadline_type' => $step->deadlineType->value,
            'deadline_cycle' => $step->deadlineCycle,
            'evaluation_forms' => array_map(fn (StepEvaluationFormInput $attached): array => [
                'review_evaluation_form_id' => $attached->reviewEvaluationFormId,
                'is_required' => $attached->isRequired,
                'is_active' => $attached->isActive,
            ], $step->evaluationForms),
        ], $this->steps)];
    }

    /** @param  PhaseDraftPayload  $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(array_map(self::step(...), $payload['steps']));
    }

    /** @param  StepPayload  $step */
    private static function step(array $step): FormPhaseDetailInput
    {
        return new FormPhaseDetailInput(
            id: null,
            formAccessControlId: $step['form_access_control_id'],
            phaseTypeId: $step['phase_type_id'],
            needsReview: $step['needs_review'],
            deadlineType: DeadlineType::from($step['deadline_type']),
            deadlineCycle: $step['deadline_cycle'],
            evaluationForms: array_map(fn (array $attached): StepEvaluationFormInput => new StepEvaluationFormInput(
                $attached['review_evaluation_form_id'],
                $attached['is_required'],
                $attached['is_active'],
            ), $step['evaluation_forms']),
        );
    }
}
