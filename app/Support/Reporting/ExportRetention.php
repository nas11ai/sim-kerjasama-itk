<?php

namespace App\Support\Reporting;

/**
 * BR-RPT-12 — berkas hasil ekspor dan cetak dihapus setelah 24 jam.
 *
 * Angkanya kebetulan sama dengan masa berlaku signed URL (BR-RPT-03), tapi
 * keduanya aturan yang berbeda dan menghasilkan penolakan yang berbeda: URL
 * kedaluwarsa berarti tautannya tidak sah lagi — berkasnya masih ada dan
 * cukup diminta tautan baru; berkas terhapus berarti tidak ada yang bisa
 * diambil sama sekali dan ekspornya harus diulang.
 *
 * Ditulis sebagai dua konstanta terpisah justru supaya keduanya bisa berubah
 * sendiri-sendiri tanpa diam-diam menyeret yang lain.
 */
final class ExportRetention
{
    /** Umur berkas sebelum dihapus penjadwal. */
    public const HOURS = 24;

    /** Masa berlaku signed URL (BR-RPT-03). */
    public const SIGNED_URL_HOURS = 24;
}
