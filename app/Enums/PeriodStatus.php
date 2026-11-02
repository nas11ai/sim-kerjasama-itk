<?php

namespace App\Enums;

use App\Models\SubmissionPeriod;
use App\Support\SubmissionPeriodGate;

/**
 * Label status periode (OPERATOR-09-01). Nilainya diturunkan dari
 * `SubmissionPeriodGate::displayStatus`, satu-satunya definisi status periode.
 * Tidak ada "Terjadwal": setiap SubmissionDate adalah batas akhir (BR-FE-01, W67).
 */
enum PeriodStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case ForceClosed = 'force_closed';
    case NoDates = 'no_dates';

    public static function of(SubmissionPeriod $period): self
    {
        return self::from(SubmissionPeriodGate::displayStatus($period));
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Dibuka',
            self::Expired => 'Ditutup',
            self::ForceClosed => 'Ditutup Operator',
            self::NoDates => 'Belum Bertanggal',
        };
    }

    /** @return array{value: string, label: string} */
    public function option(): array
    {
        return ['value' => $this->value, 'label' => $this->label()];
    }

    /**
     * Close Early — Operator menutup periode sebelum semua tenggatnya terlewat
     * (ubiquitous language, "Period Lifecycle"; W68).
     */
    public function closable(): bool
    {
        return $this === self::Active || $this === self::NoDates;
    }
}
