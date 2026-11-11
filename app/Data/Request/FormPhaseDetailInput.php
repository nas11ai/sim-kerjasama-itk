<?php

namespace App\Data\Request;

use App\Enums\DeadlineType;

/**
 * Satu baris detail tahap. `id` null berarti detail baru. BR-FE-07 + BR-FE-26 (ADR 0019) — langkah
 * menyebut jenis tenggat (`submit`/`output`) dan siklusnya; tanggalnya dibaca dari kalender periode
 * milik pengajuan, jadi tidak ada rujukan tanggal di sini.
 */
final class FormPhaseDetailInput
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $formAccessControlId,
        public readonly int $phaseTypeId,
        public readonly bool $needsReview,
        public readonly DeadlineType $deadlineType,
        public readonly ?int $deadlineCycle,
    ) {}

    /** @return array{deadline_type: DeadlineType, deadline_cycle: int|null} */
    public function stepKind(): array
    {
        return ['deadline_type' => $this->deadlineType, 'deadline_cycle' => $this->deadlineCycle];
    }
}
