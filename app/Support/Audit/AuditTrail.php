<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity;

/**
 * BR-RPT-09 — satu pintu jejak audit. Bentuk `old`/`new` seragam karena layar audit membacanya;
 * bentuk lain tampil kosong tanpa ada yang tahu. Aktor diambil dari sesi bila tak disebut, dan aksi
 * scheduler memang tanpa aktor — jangan ditambal aktor palsu.
 */
final class AuditTrail
{
    public const LOG_NAME = 'audit';

    public static function record(
        string $description,
        Model $subject,
        ?Model $causer = null,
        ?AuditChange $change = null,
    ): Activity {
        $properties = [];

        if ($change?->old !== null) {
            $properties['old'] = $change->old;
        }

        if ($change?->new !== null) {
            $properties['new'] = $change->new;
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
