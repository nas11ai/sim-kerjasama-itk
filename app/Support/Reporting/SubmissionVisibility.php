<?php

namespace App\Support\Reporting;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * BR-RPT-05, BR-RPT-10 — pengajuan mana yang boleh dibaca seseorang. Satu
 * aturan, dua pemakai (jejak audit dan ekspor): dua batas yang ditulis
 * terpisah menyimpang, dan di sini berarti data orang lain ikut terekspor.
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
        if (self::seesEverything($user)) {
            return FormSubmission::query();
        }

        return self::ownedOrJoinedBy($user);
    }

    /**
     * Pengajuan yang diketuai atau diikuti $user sebagai anggota aktif, tanpa
     * memandang `view-all` — "pengajuan saya" di daftar peneliti.
     *
     * @return Builder<FormSubmission>
     */
    public static function ownedOrJoinedBy(User $user): Builder
    {
        $joined = fn (Builder $q) => $q
            ->where('status', 'active')
            ->whereHas('userProfile', fn (Builder $p) => $p->where('user_id', $user->id));

        return FormSubmission::query()->where(function (Builder $milik) use ($user, $joined): void {
            $milik
                ->where('submitted_by', $user->id)
                // BR-SM-07 — anggota tim ikut terhitung: menyempitkan ke
                // `submitted_by` menyembunyikan pengajuan dari orang yang
                // justru ikut membuatnya.
                ->orWhereHas('researchMembers', $joined)
                // W123 — laporan monev ikut tim induknya.
                ->orWhereHas('parent', fn (Builder $parent) => $parent->whereHas('researchMembers', $joined));
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
