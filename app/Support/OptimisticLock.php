<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

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
        // Diakses lewat getAttribute(), bukan magic property, supaya PHPStan
        // tidak perlu mengenal kolom timestamp milik model konkret tertentu
        // ($model di sini bertipe Model generik).
        $updatedAtColumn = $model->getUpdatedAtColumn();
        $current = $updatedAtColumn === null ? null : $model->getAttribute($updatedAtColumn);

        $isStale = $lastUpdatedAt === null
            || !$current instanceof CarbonInterface
            || !$current->equalTo($lastUpdatedAt);

        if ($isStale) {
            abort(409, 'Data sudah diubah pengguna lain. Muat ulang halaman.');
        }
    }
}
