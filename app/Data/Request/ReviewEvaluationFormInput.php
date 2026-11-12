<?php

namespace App\Data\Request;

/**
 * Isi kartu Buat/Ubah form evaluasi di editor tahap (komposit sementara G2-W4). Field null berarti kiriman tak menyebut field:
 * tidak ada perubahan field. Wizard form builder memakai EvaluationFormInput, bukan ini.
 */
final class ReviewEvaluationFormInput
{
    /**
     * @param  list<ReviewFormFieldInput>|null  $fields
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $isRequired,
        public readonly bool $isActive,
        public readonly ?array $fields,
    ) {}

    public function details(): EvaluationFormDetailsInput
    {
        return new EvaluationFormDetailsInput($this->title, $this->description);
    }
}
