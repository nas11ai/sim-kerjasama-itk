<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu langkah di editor ADMIN-08-09 — isi draf atau versi terbaru (BR-FE-33). Tanpa id dan
 * hitungan pakai: versi terbit beku (BR-FE-32), jadi menyunting langkah tidak menyentuh pengajuan
 * mana pun. Tanggal langkah milik kalender periode (ADR 0019).
 */
final class PhaseDetailEditData extends Data
{
    /** @param  list<StepEvaluationFormData>  $evaluationForms  BR-FE-30 — urut seperti dipasang */
    public function __construct(
        public int $order,
        public int $formAccessControlId,
        public string $formTitle,
        public string $accessLabel,
        public int $phaseTypeId,
        public string $phaseTypeName,
        public bool $needsReview,
        public string $deadlineType,
        public ?int $deadlineCycle,
        public string $deadlineLabel,
        public array $evaluationForms,
    ) {}
}
