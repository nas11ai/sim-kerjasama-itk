<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W142 — flag sidebar "Luaran Penelitian": tampil bila daftarnya berisi. Satu builder dengan
 * daftarnya supaya menu dan halaman tidak pernah berselisih.
 *
 * @method static bool run(User $user)
 */
class HasOutputSubmission implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): bool
    {
        return ResearcherOutputList::query($user)->exists();
    }
}
