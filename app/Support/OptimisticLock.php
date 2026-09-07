<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * BR-FE-14 / BR-SM-12 — mencegah concurrent edit.
 *
 * Controller mengirim `last_updated_at` dari payload; kalau tidak cocok dengan
 * nilai di database berarti ada yang sudah menyimpan duluan.
 */
final class OptimisticLock
{
    public static function assertNotStale(Model $model, ?string $lastUpdatedAt): void
    {
        // Dua kegagalan yang berbeda, dan membedakannya penting bagi klien.
        //
        // `last_updated_at` yang **tidak dikirim** adalah permintaan yang cacat
        // bentuknya — 422, dan mengulanginya apa adanya tidak akan berhasil.
        // `last_updated_at` yang **sudah basi** adalah tabrakan penyuntingan —
        // 409, dan jalan keluarnya memang memuat ulang lalu mencoba lagi.
        //
        // Menyatukan keduanya jadi 409 membuat klien yang otomatis memuat
        // ulang pada 409 berputar selamanya untuk field yang memang tidak
        // pernah ia kirim.
        if ($lastUpdatedAt === null) {
            throw ValidationException::withMessages([
                'last_updated_at' => 'Waktu perubahan terakhir wajib disertakan saat menyimpan.',
            ]);
        }

        // Diakses lewat getAttribute(), bukan magic property, supaya PHPStan
        // tidak perlu mengenal kolom timestamp milik model konkret tertentu
        // ($model di sini bertipe Model generik).
        $updatedAtColumn = $model->getUpdatedAtColumn();
        $current = $updatedAtColumn === null ? null : $model->getAttribute($updatedAtColumn);

        $isStale = !$current instanceof CarbonInterface || !$current->equalTo($lastUpdatedAt);

        if ($isStale) {
            abort(409, 'Data sudah diubah pengguna lain. Muat ulang halaman.');
        }
    }
}
