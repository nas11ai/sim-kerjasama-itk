<?php

namespace App\Data\Request;

/** Satu field form evaluasi reviewer dari wizard, urut seperti di layar. */
final class EvaluationFieldInput
{
    /**
     * @param  list<array{label: string, value: string}>  $options
     */
    public function __construct(
        public readonly int $fieldTypeId,
        public readonly string $label,
        public readonly ?string $description,
        public readonly bool $isRequired,
        public readonly array $options,
    ) {}
}
