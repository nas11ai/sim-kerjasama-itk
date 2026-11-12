<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * BR-REV-18, BR-REV-19 — satu Form Evaluasi terpasang di satu langkah direview, dilihat dari satu
 * periode: versi pakuan angkatannya dan apakah versi terbaru boleh diterapkan. `pinId` null = belum
 * ada yang mulai menilai, jadi angkatan itu otomatis memakai `latestLabel`.
 */
final class CohortEvaluationVersionData extends Data
{
    public function __construct(
        public string $phaseTitle,
        public string $stepLabel,
        public string $formTitle,
        public ?int $pinId,
        public ?string $pinnedLabel,
        public string $latestLabel,
        public int $responseCount,
        public int $decidedCount,
        public bool $canApply,
    ) {}
}
