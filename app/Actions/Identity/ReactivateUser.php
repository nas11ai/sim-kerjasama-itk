<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Notifications\AccountReactivatedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-20 — memulihkan kemampuan login, dan hanya itu. Kepemilikan yang sudah dialihkan
 * (BR-SM-09), penugasan `replaced` (BR-REV-10), dan masa tugas reviewer (BR-REV-09) tidak
 * disentuh; peran, hak akses, dan profil memang tak pernah diubah deaktivasi (BR-IAM-05).
 *
 * @method static User run(User $actor, User $user)
 */
class ReactivateUser
{
    use AsAction;

    public function handle(User $actor, User $user): User
    {
        // IAM-REACT-05 — akun yang sudah aktif: tak ada keputusan, jadi tak ada jejak maupun kabar.
        if ($user->is_active) {
            return $user;
        }

        $user->forceFill(['is_active' => true])->save();

        // BR-IAM-15 — keputusan orang lain atas akunnya.
        Deliver::to($user, new AccountReactivatedNotification);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Akun diaktifkan kembali', $user, $actor);

        return $user;
    }
}
