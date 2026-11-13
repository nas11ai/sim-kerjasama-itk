<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use App\Models\User;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-18 — user yang bisa dipindah dari panel unit (W120): profilnya di subtree struktural unit
 * itu, aktif maupun nonaktif. Dicari lewat nama atau email; tanpa pencarian, 20 pertama.
 *
 * @phpstan-type Candidate array{value: string, label: string, hint: string, organizationId: int}
 *
 * @method static list<Candidate> run(Organization $unit, string $search)
 */
class OrganizationMembers implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 20;

    /** @return list<Candidate> */
    public function handle(Organization $unit, string $search): array
    {
        $pattern = LikePattern::contains(trim($search));

        $users = User::query()
            ->whereHas('userProfile', fn (Builder $profile) => $profile->whereIn('organization_id', OrganizationSubtree::run($unit->id)))
            ->where(fn (Builder $q) => $q->whereLike('name', $pattern)->orWhereLike('email', $pattern))
            ->with('userProfile.organization:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        return array_values($users->map(fn (User $user): array => $this->candidate($user))->all());
    }

    /** @return Candidate */
    private function candidate(User $user): array
    {
        $organization = $user->userProfile?->organization;

        return [
            'value' => (string) $user->id,
            'label' => $user->name,
            'hint' => ($organization->name ?? '-').' · '.($user->is_active ? 'Aktif' : 'Nonaktif'),
            'organizationId' => $organization->id ?? 0,
        ];
    }
}
