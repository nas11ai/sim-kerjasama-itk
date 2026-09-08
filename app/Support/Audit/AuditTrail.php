<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity;

/**
 * BR-RPT-09 — satu pintu untuk menulis jejak audit.
 *
 * Bentuk propertinya diseragamkan (`old`/`new`) karena layar audit membaca
 * keduanya untuk menampilkan "nilai lama → nilai baru". Kalau setiap Action
 * memilih bentuknya sendiri, layar itu harus mengenali sebanyak mungkin
 * bentuk — dan yang tidak dikenalinya akan tampil kosong tanpa ada yang tahu.
 *
 * Aktornya diambil dari sesi kalau tidak disebut. Aksi yang dijalankan
 * scheduler memang tidak punya aktor, dan itu bukan kekurangan yang perlu
 * ditutup dengan aktor palsu.
 */
final class AuditTrail
{
    public const LOG_NAME = 'audit';

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(
        string $description,
        Model $subject,
        ?Model $causer = null,
        ?array $old = null,
        ?array $new = null,
    ): Activity {
        $properties = [];

        if ($old !== null) {
            $properties['old'] = $old;
        }

        if ($new !== null) {
            $properties['new'] = $new;
        }

        $activity = activity(self::LOG_NAME)
            ->performedOn($subject)
            ->causedBy($causer ?? Auth::user())
            ->withProperties($properties)
            ->log($description);

        // `log()` bertipe Activity|null karena logging bisa dimatikan global.
        // Kalau itu terjadi kita ingin tahu di sini, bukan di layar audit yang
        // diam-diam kosong.
        if (!$activity instanceof Activity) {
            throw new AuditDisabledException(
                "Jejak audit dimatikan saat mencatat \"{$description}\" — cakupan audit (BR-RPT-09) tidak lagi utuh."
            );
        }

        return $activity;
    }
}
