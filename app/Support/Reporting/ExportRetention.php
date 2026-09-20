<?php

namespace App\Support\Reporting;

/**
 * BR-RPT-12 — berkas hasil ekspor dan cetak dihapus setelah 24 jam. Angkanya
 * kebetulan sama dengan masa berlaku signed URL (BR-RPT-03), tapi dua konstanta
 * terpisah supaya keduanya bisa berubah sendiri tanpa menyeret yang lain.
 */
final class ExportRetention
{
    /** Umur berkas sebelum dihapus penjadwal; habis berarti ekspor diulang. */
    public const HOURS = 24;

    /** Masa berlaku signed URL (BR-RPT-03); habis cukup minta tautan baru. */
    public const SIGNED_URL_HOURS = 24;
}
