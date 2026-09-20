<?php

namespace App\Support\Lifecycle;

use Illuminate\Validation\ValidationException;

/**
 * BR-FE-19 s/d BR-FE-22, sejalan dengan BR-SC-01 dan BR-IAM-08 — menolak
 * penghapusan selama masih ada yang merujuk. Dipanggil dari `booted()` model,
 * bukan hanya Action: hapus lewat relasi dan kode lama tak melewati Action.
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

        // Penolakannya **menyebut angka**: "tidak dapat dihapus" saja memaksa
        // Operator menebak, dan tebakan pada operasi merusak adalah cara data
        // hilang.
        $rincian = implode(', ', array_map(
            static fn (string $nama, int $jumlah): string => "{$jumlah} {$nama}",
            array_keys($penghalang),
            $penghalang,
        ));

        // Kegagalan foreign key tidak dihitung sebagai penegakan — yang sampai
        // ke layar harus aturan domain, bukan SQLSTATE.
        throw ValidationException::withMessages([
            'id' => "{$label} ini masih dirujuk {$rincian}, jadi tidak dapat dihapus. "
                .'Nonaktifkan saja agar tidak lagi dipakai pengajuan baru, tanpa menghilangkan yang sudah ada.',
        ]);
    }
}
