<?php

namespace App\Support\Format;

/**
 * W150 — angka anggaran yang dipertanggungjawabkan ditampilkan utuh ("Rp 6.500.000"), tidak
 * dibulatkan; frontend tidak memformat ulang.
 */
final class Rupiah
{
    public static function format(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
