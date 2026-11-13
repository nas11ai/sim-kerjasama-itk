<?php

namespace App\Data\Request;

/** Nama pustaka Formulir Evaluasi: judul dan deskripsi, tak berversi (BR-REV-18). */
final class EvaluationFormDetailsInput
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
    ) {}

    /** @return array{title: string, description: string|null} */
    public function attributes(): array
    {
        return ['title' => $this->title, 'description' => $this->description];
    }
}
