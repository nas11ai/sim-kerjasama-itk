<?php

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Identity\RoleCatalog;

/**
 * W155 — kolom Pelaku Audit Log: "{nama} ({semua peran DDD, urutan RoleCatalog})". Satu label
 * pilihan salah separuh waktu bagi user dua peran. Tanpa aktor → "Sistem" (scheduler/CLI).
 */
final class AuditActorLabel
{
    public const SYSTEM = 'Sistem';

    public static function for(AuditEntry $entry): string
    {
        $causer = $entry->causer;

        if (!$causer instanceof User) {
            // Aktor yang akunnya sudah terhapus tetap bukan "Sistem".
            return $entry->causer_id === null ? self::SYSTEM : "User #{$entry->causer_id}";
        }

        $held = RoleCatalog::namesOf($causer->roles);
        $labels = array_values(array_map(
            RoleCatalog::label(...),
            array_filter(array_keys(RoleCatalog::LABELS), fn (string $role): bool => in_array($role, $held, true)),
        ));

        return $labels === [] ? $causer->name : $causer->name.' ('.implode(', ', $labels).')';
    }
}
