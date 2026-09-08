<?php

namespace App\Support\Reporting;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * BR-RPT-05, BR-RPT-10 — pengajuan mana yang boleh dibaca seseorang.
 *
 * Satu aturan, dua pemakai: jejak audit dan ekspor. Ekspor sengaja **tidak**
 * melahirkan konsep scoping kedua — query dan batasnya sama, hanya
 * pemanggilnya berbeda. Dua batas yang ditulis terpisah adalah dua batas yang
 * cepat atau lambat menyimpang, dan yang menyimpang di sini berarti data
 * orang lain ikut terekspor.
 *
 * Anggota tim ikut terhitung: BR-SM-07 memberi mereka hak baca, dan
 * menyempitkannya ke `submitted_by` akan menyembunyikan pengajuan dari orang
 * yang justru ikut membuatnya.
 */
final class SubmissionVisibility
{
    public const READ_ALL_PERMISSION = 'submissions.view-all';

    public static function seesEverything(User $user): bool
    {
        return $user->can(self::READ_ALL_PERMISSION);
    }

    /**
     * Pengajuan yang boleh dibaca $user. Operator mendapat seluruhnya.
     *
     * @return Builder<FormSubmission>
     */
    public static function for(User $user): Builder
    {
        $query = FormSubmission::query();

        if (self::seesEverything($user)) {
            return $query;
        }

        return $query->where(function (Builder $milik) use ($user): void {
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

    /**
     * Bentuk subquery id — dipakai `whereIn` tanpa memateriakan daftarnya.
     *
     * @return Builder<FormSubmission>
     */
    public static function idsFor(User $user): Builder
    {
        return self::for($user)->select('id');
    }
}
