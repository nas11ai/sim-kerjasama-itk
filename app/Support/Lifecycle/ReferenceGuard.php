<?php

namespace App\Support\Lifecycle;

use Illuminate\Validation\ValidationException;

/**
 * BR-FE-19 s/d BR-FE-22, sejalan dengan BR-SC-01 dan BR-IAM-08 — menolak
 * penghapusan selama masih ada yang merujuk.
 *
 * Penolakannya **menyebut angka**. Itu bukan kesopanan: "tidak dapat dihapus"
 * saja memaksa Operator menebak apakah ia salah memilih baris atau memang
 * harus menonaktifkan, dan tebakan pada operasi merusak adalah cara data
 * hilang. Kegagalan foreign key juga tidak dihitung sebagai penegakan — yang
 * sampai ke layar harus aturan domain, bukan SQLSTATE.
 *
 * Dipanggil dari `booted()` model, bukan hanya dari Action, karena penghapusan
 * lewat relasi dan lewat kode lama tidak melewati Action mana pun.
 */
final class ReferenceGuard
{
    /**
     * @param  array<string, int>  $pemakai  label pemakai => jumlahnya
     *
     * @throws ValidationException
     */
    public static function assertUnreferenced(string $label, array $pemakai): void
    {
        $penghalang = array_filter($pemakai, static fn (int $jumlah): bool => $jumlah > 0);

        if ($penghalang === []) {
            return;
        }

        $rincian = implode(', ', array_map(
            static fn (string $nama, int $jumlah): string => "{$jumlah} {$nama}",
            array_keys($penghalang),
            $penghalang,
        ));

        throw ValidationException::withMessages([
            'id' => "{$label} ini masih dirujuk {$rincian}, jadi tidak dapat dihapus. "
                .'Nonaktifkan saja agar tidak lagi dipakai pengajuan baru, tanpa menghilangkan yang sudah ada.',
        ]);
    }
}
