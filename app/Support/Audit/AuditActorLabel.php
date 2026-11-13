<?php

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Identity\RoleCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * W155 — kolom Pelaku Log Audit: "{nama} ({semua peran DDD, urutan RoleCatalog})". Satu label
 * pilihan salah separuh waktu bagi user dua peran. Tanpa aktor → "Sistem" (scheduler/CLI).
 */
final class AuditActorLabel
{
    public const SYSTEM = 'Sistem';

    /**
     * E2E-08 — dipasang di `with(['causer' => AuditActorLabel::eager(...), 'causer.roles'])`:
     * baris user ber-deleted_at (data lama, sebelum BR-IAM-19) tetap bernama.
     *
     * @param  Relation<*, *, *>  $causer
     */
    public static function eager(Relation $causer): void
    {
        if (!$causer instanceof MorphTo) {
            return;
        }

        $causer->constrain([User::class => fn (Builder $user) => $user->withoutGlobalScope(SoftDeletingScope::class)]);
    }

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
