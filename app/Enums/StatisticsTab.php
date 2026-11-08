<?php

namespace App\Enums;

/**
 * W152/W156 — tab Dashboard Statistik; tab = route, jadi nilai ini segmen URL-nya.
 */
enum StatisticsTab: string
{
    case Overview = 'overview';
    case Now = 'now';
    case History = 'history';
    case FollowUp = 'follow-up';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Ringkasan',
            self::Now => 'Saat Ini',
            self::History => 'Riwayat',
            self::FollowUp => 'Tindak Lanjut',
        };
    }

    /**
     * Saat Ini dan Tindak Lanjut adalah keadaan sekarang, jadi tidak dibatasi tahun.
     *
     * @return list<'year'|'period'|'scheme'>
     */
    public function filterKeys(): array
    {
        return match ($this) {
            self::Now, self::FollowUp => ['period', 'scheme'],
            self::Overview, self::History => ['year', 'period', 'scheme'],
        };
    }
}
