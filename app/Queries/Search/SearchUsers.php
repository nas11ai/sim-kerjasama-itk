<?php

namespace App\Queries\Search;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SearchHitData;
use App\Models\User;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * K2 grup User — cocok nama atau NIDN, nama yang diawali istilah lebih dulu.
 *
 * @method static list<SearchHitData> run(User $viewer, string $term)
 */
class SearchUsers implements ChangesNoState
{
    use AsAction;

    /** @return list<SearchHitData> */
    public function handle(User $viewer, string $term): array
    {
        // W47 — hanya pemegang users.verify atau users.manage (UserPolicy::viewAny).
        if (!$viewer->can('viewAny', User::class)) {
            return [];
        }

        $pattern = LikePattern::contains($term);

        return array_values(User::query()
            ->where(fn (Builder $any) => $any
                ->whereLike('name', $pattern)
                ->orWhereHas('userProfile', fn (Builder $profile) => $profile->whereLike('nidn', $pattern)))
            ->with('userProfile:id,user_id,nidn')
            ->orderByRaw('CASE WHEN users.name ILIKE ? THEN 0 ELSE 1 END', [LikePattern::startsWith($term)])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(GlobalSearch::LIMIT)
            ->get()
            ->map(fn (User $user): SearchHitData => new SearchHitData(
                id: $user->id,
                label: $user->name,
                sublabel: self::sublabel($user),
                url: route('admin.users.show', $user->id),
            ))
            ->all());
    }

    private static function sublabel(User $user): string
    {
        $nidn = $user->userProfile?->nidn;
        $identity = $nidn !== null && $nidn !== '' ? "NIDN {$nidn}" : $user->email;

        return $user->is_active ? $identity : "{$identity} · Nonaktif";
    }
}
