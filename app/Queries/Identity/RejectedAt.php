<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\Identity\RejectNidnVerification;
use App\Models\AuditEntry;
use App\Models\UserProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Ditolak pada" (W53, menutup W5): tabel profil tidak menyimpannya, jejak audit
 * penolakan terbaru yang menyimpan — pola yang sama dengan FirstSubmitAt (W7).
 */
final class RejectedAt implements ChangesNoState
{
    /** @return Builder<AuditEntry> */
    public static function subquery(): Builder
    {
        return self::entries()
            ->selectRaw('max(created_at)')
            ->whereColumn('subject_id', 'user_profiles.id');
    }

    public static function of(UserProfile $profile): ?CarbonImmutable
    {
        $at = self::entries()->where('subject_id', $profile->id)->max('created_at');

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    /** @return Builder<AuditEntry> */
    private static function entries(): Builder
    {
        return AuditEntry::query()->auditOnly()
            ->where('subject_type', UserProfile::class)
            ->where('description', RejectNidnVerification::AUDIT_DESCRIPTION);
    }
}
