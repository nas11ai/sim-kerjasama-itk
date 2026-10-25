<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Nama route tujuan setelah login bila tidak ada URL `intended`.
 *
 * @method static string run(User $user)
 */
class PostLoginDestination implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): string
    {
        if ($user->can('users.manage')) {
            return 'admin.dashboard';
        }

        // BR-IAM-04 — akun pending dan ditolak boleh masuk, dan mendarat di layar yang
        // menjelaskan kenapa tombol Submit-nya masih tertutup (IAM-VERIFY-01).
        if ($user->userProfile !== null && !$user->userProfile->verification_status->canSubmit()) {
            return 'verification.status';
        }

        return 'user.dashboard';
    }
}
