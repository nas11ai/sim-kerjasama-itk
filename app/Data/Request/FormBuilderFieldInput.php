<?php

namespace App\Data\Request;

/** Satu field form dari wizard; opsinya label saja, urut seperti di layar. */
final class FormBuilderFieldInput
{
    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public readonly int $fieldTypeId,
        public readonly string $label,
        public readonly bool $isRequired,
        public readonly array $options,
    ) {}
}
