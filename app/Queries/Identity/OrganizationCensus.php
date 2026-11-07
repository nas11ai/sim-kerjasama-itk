<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Result\OrganizationCensusData;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sensus penghuni unit dan subtree strukturalnya (OrganizationSubtree, W116).
 *
 * @method static OrganizationCensusData run(Organization $organization)
 */
class OrganizationCensus implements ChangesNoState
{
    use AsAction;

    public function handle(Organization $organization): OrganizationCensusData
    {
        $subtree = OrganizationSubtree::run($organization->id);
        $descendants = array_values(array_diff($subtree, [$organization->id]));

        return new OrganizationCensusData(
            directProfiles: $this->profiles([$organization->id])->count(),
            directActiveUsers: $this->activeUsers([$organization->id])->count(),
            subtreeProfiles: $this->profiles($subtree)->count(),
            subtreeActiveUsers: $this->activeUsers($subtree)->count(),
            unitsWithActiveUsers: $this->profiles($descendants)
                ->whereHas('user', static fn (Builder $user) => $user->where('is_active', true))
                ->distinct()
                ->count('organization_id'),
            // Predikat yang sama dengan undangan yang dicabut DeleteOrganization (BR-IAM-06).
            pendingInvitations: InvitationToken::query()
                ->whereIn('organization_id', $subtree)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    /**
     * @param  list<int>  $units
     * @return Builder<UserProfile>
     */
    private function profiles(array $units): Builder
    {
        return UserProfile::query()->whereIn('organization_id', $units);
    }

    /**
     * @param  list<int>  $units
     * @return Builder<User>
     */
    private function activeUsers(array $units): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('id', $this->profiles($units)->select('user_id'));
    }
}
