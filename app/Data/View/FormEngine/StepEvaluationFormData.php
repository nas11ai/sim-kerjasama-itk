<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu pemasangan Formulir Evaluasi di langkah editor tahap (BR-FE-30), bagian isi draf/versi
 * (BR-FE-33). `title` kosong = form-nya sudah dihapus dari pustaka sesudah draf disimpan.
 */
final class StepEvaluationFormData extends Data
{
    public function __construct(
        public int $formId,
        public string $title,
        public string $versionLabel,
        public bool $isRequired,
        public bool $isActive,
    ) {}
}
