<?php

namespace App\Data\Request;

use App\Enums\DeadlineType;

/**
 * Satu baris detail tahap. `id` null berarti detail baru; `id` terisi = langkah versi terbaru yang disalin. BR-FE-07 + BR-FE-26 (ADR 0019) — langkah
 * menyebut jenis tenggat (`submit`/`output`) dan siklusnya; tanggalnya dibaca dari kalender periode
 * milik pengajuan, jadi tidak ada rujukan tanggal di sini.
 */
final class FormPhaseDetailInput
{
    /** @param  list<StepEvaluationFormInput>  $evaluationForms  BR-FE-30 — Formulir Evaluasi terpasang, urut */
    public function __construct(
        public readonly ?int $id,
        public readonly int $formAccessControlId,
        public readonly int $phaseTypeId,
        public readonly bool $needsReview,
        public readonly DeadlineType $deadlineType,
        public readonly ?int $deadlineCycle,
        public readonly array $evaluationForms = [],
    ) {}

    /** @param  list<StepEvaluationFormInput>  $evaluationForms */
    public function withEvaluationForms(array $evaluationForms): self
    {
        return new self($this->id, $this->formAccessControlId, $this->phaseTypeId, $this->needsReview, $this->deadlineType, $this->deadlineCycle, $evaluationForms);
    }

    /** @return array{deadline_type: DeadlineType, deadline_cycle: int|null} */
    public function stepKind(): array
    {
        return ['deadline_type' => $this->deadlineType, 'deadline_cycle' => $this->deadlineCycle];
    }

    /** Padanan `FormPhaseDetail::opensSubmission()` untuk masukan: `submit` tanpa siklus (BR-FE-28). */
    public function opensSubmission(): bool
    {
        return $this->deadlineType === DeadlineType::Submit && $this->deadlineCycle === null;
    }
}
