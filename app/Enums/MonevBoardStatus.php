<?php

namespace App\Enums;

use App\Data\View\Monev\MonevCycleData;

/**
 * Status satu induk di OPERATOR-05-01, dibaca dari siklus berjalan (siklus pertama yang
 * belum `Done`). Turunan `MonevCycles`, bukan kolom (W126 menyimpan hanya `cycle_opened_at`).
 */
enum MonevBoardStatus: string
{
    case NotOpened = 'not_opened';
    case AwaitingReport = 'awaiting_report';
    case ReportIn = 'report_in';
    case Completed = 'completed';

    public static function of(?MonevCycleData $current): self
    {
        if ($current === null) {
            return self::Completed;
        }

        if ($current->state === MonevCycleState::Submitted) {
            return self::ReportIn;
        }

        return $current->openedAt === null ? self::NotOpened : self::AwaitingReport;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotOpened => 'Belum Aktif',
            self::AwaitingReport => 'Menunggu Laporan',
            self::ReportIn => 'Laporan Masuk',
            self::Completed => 'Selesai',
        };
    }
}
