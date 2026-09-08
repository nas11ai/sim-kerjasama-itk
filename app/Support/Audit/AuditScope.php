<?php

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * BR-RPT-05 — siapa membaca jejak audit yang mana.
 *
 * Operator membaca seluruh sistem, peneliti hanya pengajuannya sendiri. Batas
 * yang sama dipakai ekspor (BR-RPT-10) lewat kelas ini juga — ekspor sengaja
 * tidak melahirkan konsep scoping kedua, karena dua batas yang ditulis
 * terpisah adalah dua batas yang cepat atau lambat menyimpang.
 *
 * "Pengajuan miliknya" memakai `isAccessibleBy` lewat daftar id, bukan sekadar
 * `submitted_by`: anggota tim juga berhak membaca jejak pengajuan yang ia
 * ikuti (BR-SM-07), dan menyempitkannya ke ketua pengaju akan menyembunyikan
 * riwayat dari orang yang justru ikut membuatnya.
 */
final class AuditScope
{
    public const READ_ALL_PERMISSION = 'submissions.view-all';

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
                        ->whereIn('subject_id', self::readableSubmissions($user));
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

    /**
     * Subquery, bukan daftar id yang dimaterialisasi: peneliti dengan banyak
     * pengajuan akan menghasilkan `IN (...)` sepanjang jumlah pengajuannya,
     * dan itu tumbuh tanpa batas atas.
     *
     * @return Builder<FormSubmission>
     */
    private static function readableSubmissions(User $user): Builder
    {
        return FormSubmission::query()
            ->select('id')
            ->where(function (Builder $milik) use ($user): void {
                $milik
                    ->where('submitted_by', $user->id)
                    ->orWhereHas(
                        'researchMembers',
                        fn (Builder $q) => $q
                            ->where('status', 'active')
                            ->whereHas('userProfile', fn (Builder $p) => $p->where('user_id', $user->id))
                    );
            });
    }
}
