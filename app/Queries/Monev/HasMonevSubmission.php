<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W128 — flag sidebar "Daftar Monev": tampil bila daftarnya berisi. Satu builder dengan
 * daftarnya supaya menu dan halaman tidak pernah berselisih.
 *
 * @method static bool run(User $user)
 */
class HasMonevSubmission implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): bool
    {
        return ResearcherMonevList::query($user)->exists();
    }
}
