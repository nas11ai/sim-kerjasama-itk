<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Identity\AppointmentCandidateData;
use App\Models\User;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-11 — penunjukan memilih user yang sudah terdaftar (IAM-REVIEWER-03), aktif (BR-IAM-05),
 * dan punya profil. Dicari lewat nama, email, atau NIDN; kurang dari 2 huruf tidak dicari.
 *
 * @method static list<AppointmentCandidateData> run(string $search)
 */
class AppointmentCandidates implements ChangesNoState
{
    use AsAction;

    private const MIN_SEARCH = 2;

    private const LIMIT = 20;

    /** @return list<AppointmentCandidateData> */
    public function handle(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < self::MIN_SEARCH) {
            return [];
        }

        $pattern = LikePattern::contains($search);

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('userProfile')
            ->where(fn (Builder $q) => $q
                ->whereLike('name', $pattern)
                ->orWhereLike('email', $pattern)
                ->orWhereHas('userProfile', fn (Builder $profile) => $profile->whereLike('nidn', $pattern)))
            ->with(['userProfile', 'organization:organizations.id,name', 'reviewers'])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        return array_values($users->map(AppointmentCandidateData::fromModel(...))->all());
    }
}
