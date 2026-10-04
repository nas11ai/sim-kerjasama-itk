<?php

namespace App\Data\Request;

/** Masukan satu tahap formulir beserta seluruh detailnya, urut seperti di layar. */
final class FormPhaseInput
{
    /**
     * @param  list<FormPhaseDetailInput>  $details
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $isActive,
        public readonly array $details,
    ) {}
}
