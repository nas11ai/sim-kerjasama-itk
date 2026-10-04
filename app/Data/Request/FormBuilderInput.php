<?php

namespace App\Data\Request;

/** Masukan wizard enam langkah: form beserta akses, tahap, form evaluasi, dan periodenya. */
final class FormBuilderInput
{
    /**
     * @param  list<FormBuilderFieldInput>  $fields
     * @param  list<array{permission: string, organizationId: int}>  $accessControls
     * @param  list<EvaluationFormInput>  $evaluationForms
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $formTypeId,
        public readonly bool $isActive,
        public readonly array $fields,
        public readonly array $accessControls,
        public readonly FormBuilderPhaseInput $phase,
        public readonly array $evaluationForms,
        public readonly FormBuilderPeriodInput $period,
    ) {}
}
