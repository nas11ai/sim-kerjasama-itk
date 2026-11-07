<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\InvitationToken;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-22 — undangan unit itu yang berlaku lagi saat unitnya diaktifkan kembali: predikat
 * `InvitationToken::isValid()` (BR-IAM-06) tanpa syarat organisasi aktif. Hanya unit itu, bukan
 * subtree, karena turunannya tetap nonaktif.
 *
 * @method static int run(Organization $organization)
 */
class RevivableInvitations implements ChangesNoState
{
    use AsAction;

    public function handle(Organization $organization): int
    {
        return InvitationToken::query()
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
            ->count();
    }
}
