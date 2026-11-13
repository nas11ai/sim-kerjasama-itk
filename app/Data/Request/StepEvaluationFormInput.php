<?php

namespace App\Data\Request;

/** BR-FE-30 — satu pemasangan Formulir Evaluasi di langkah; urutannya = indeks di daftar langkah itu. */
final class StepEvaluationFormInput
{
    public function __construct(
        public readonly int $reviewEvaluationFormId,
        public readonly bool $isRequired,
        public readonly bool $isActive,
    ) {}
}
