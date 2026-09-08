<?php

namespace App\Models;

use App\Support\Audit\AppendOnlyViolation;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * BR-RPT-04 — jejak audit bersifat append-only.
 *
 * Dua cara melanggarnya ditutup terpisah. Melarang DELETE saja tetap
 * membiarkan UPDATE menulis ulang sejarah — dan itu justru pelanggaran yang
 * lebih berbahaya, karena hasilnya tetap tampak sah.
 *
 * Dijaga di model, bukan di Policy: Policy hanya dilewati kalau ada yang
 * memanggilnya, sedangkan penjagaan di sini ikut menutup jalur mana pun yang
 * memakai Eloquent — termasuk tinker dan seeder yang tidak melewati HTTP.
 *
 * @property string $description
 * @property array<string, mixed>|null $properties
 */
class AuditEntry extends Activity
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new AppendOnlyViolation(
                'Entri audit tidak dapat disunting — jejak audit bersifat append-only (BR-RPT-04). '
                .'Koreksi dicatat sebagai tindakan baru, bukan dengan mengubah entri lama.'
            );
        });

        static::deleting(function (): never {
            throw new AppendOnlyViolation(
                'Entri audit tidak dapat dihapus — jejak audit bersifat append-only (BR-RPT-04).'
            );
        });
    }

    /**
     * Hanya entri yang ditulis lewat AuditTrail, bukan seluruh isi
     * activity_log: log lain (mis. `budget`) punya pembacanya sendiri.
     *
     * @param  Builder<AuditEntry>  $query
     * @return Builder<AuditEntry>
     */
    public function scopeAuditOnly(Builder $query): Builder
    {
        return $query->where('log_name', AuditTrail::LOG_NAME);
    }
}
