<?php

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * BR-RPT-05 — siapa membaca jejak audit yang mana: Operator seluruh sistem,
 * peneliti hanya pengajuannya sendiri. Ekspor (BR-RPT-10) memakai kelas ini
 * juga — dua batas yang ditulis terpisah cepat atau lambat menyimpang.
 */
final class AuditScope
{
    public const READ_ALL_PERMISSION = SubmissionVisibility::READ_ALL_PERMISSION;

    /**
     * @return Builder<AuditEntry>
     */
    public static function forUser(User $user): Builder
    {
        $query = AuditEntry::query()->auditOnly();

        if ($user->can(self::READ_ALL_PERMISSION)) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped
                ->where(function (Builder $onSubmission) use ($user): void {
                    $onSubmission
                        ->where('subject_type', FormSubmission::class)
                        // BR-SM-07 — "miliknya" lewat `idsFor`, bukan
                        // `submitted_by`: anggota tim ikut berhak membaca
                        // jejak pengajuan yang ia ikuti.
                        ->whereIn('subject_id', SubmissionVisibility::idsFor($user));
                })
                // Entri yang subjeknya bukan pengajuan — mis. perubahan
                // profilnya sendiri — tetap miliknya untuk dibaca.
                ->orWhere(function (Builder $onSelf) use ($user): void {
                    $onSelf
                        ->where('causer_type', User::class)
                        ->where('causer_id', $user->id)
                        ->where('subject_type', '!=', FormSubmission::class);
                });
        });
    }
}
