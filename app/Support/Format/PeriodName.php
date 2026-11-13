<?php

namespace App\Support\Format;

/**
 * "Periode 2026" untuk nama periode. Operator sering sudah menulis "Periode ..." di namanya, jadi
 * awalan hanya ditambahkan bila belum ada (Paket 2 P1: "Periode Periode 2026"). Pasangan frontend:
 * formatPeriodName() di resources/js/lib/format.ts.
 */
final class PeriodName
{
    public static function format(string $name): string
    {
        $name = trim($name);

        return preg_match('/^periode\b/i', $name) === 1 ? $name : "Periode {$name}";
    }
}
