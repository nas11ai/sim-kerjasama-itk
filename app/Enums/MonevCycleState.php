<?php

namespace App\Enums;

/**
 * Keadaan satu siklus monev bagi sebuah induk, dihitung `MonevCycles` dari BR-MON-01/02/05/08.
 * Turunan keadaan, bukan kolom: tidak ada tombol yang memindahkannya.
 */
enum MonevCycleState: string
{
    case Done = 'done';
    case Submitted = 'submitted';
    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Selesai',
            self::Submitted => 'Disubmit',
            self::Open => 'Terbuka',
            self::Closed => 'Ditutup',
            self::Locked => 'Terkunci',
            self::Frozen => 'Dibekukan',
        };
    }
}
