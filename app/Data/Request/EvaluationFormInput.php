<?php

namespace App\Data\Request;

/** Satu form evaluasi reviewer yang dipasang wizard ke setiap detail tahap yang direview. */
final class EvaluationFormInput
{
    /**
     * @param  list<EvaluationFieldInput>  $fields
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $isRequired,
        public readonly array $fields,
    ) {}
}
