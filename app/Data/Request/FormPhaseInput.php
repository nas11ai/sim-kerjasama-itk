<?php

namespace App\Data\Request;

/** BR-FE-33 — bagian tahap yang tidak berversi: nama pustaka, deskripsi, status aktif. Langkah lewat PhaseDraftInput. */
final class FormPhaseInput
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $isActive,
    ) {}
}
