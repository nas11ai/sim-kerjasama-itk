<?php

namespace App\Actions\Identity;

use App\Models\InvitationToken;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-16 — Operator mencabut tautan undangan.
 *
 * Sebab keempat sebuah token tidak berlaku (BR-IAM-06), dan satu-satunya yang bisa dipakai
 * segera: tanpa pencabutan, tautan yang bocor hanya bisa ditunggu kedaluwarsa atau habis kuota.
 *
 * @method static void run(User $actor, InvitationToken $invitation)
 */
class RevokeInvitation
{
    use AsAction;

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, InvitationToken $invitation): void
    {
        if (!$actor->can('users.manage')) {
            throw new AuthorizationException('Hanya Operator yang dapat mencabut undangan registrasi.');
        }

        // Mencabut yang sudah tercabut bukan kesalahan yang perlu diteriakkan;
        // yang penting keadaan akhirnya, dan jejak pertamanya sudah ada.
        if ($invitation->revoked_at !== null) {
            return;
        }

        $sisaKuota = $invitation->max_uses === null
            ? null
            : max(0, $invitation->max_uses - $invitation->used_count);

        // **Tidak menghapus** token, hanya menyetel `revoked_at`: jejak kenapa sebuah tautan
        // dimatikan sama pentingnya dengan matinya. Akun yang terlanjur lahir tidak terpengaruh —
        // mereka sudah diperiksa saat mendaftar, dan mencabut aksesnya keputusan lain (BR-IAM-05).
        $invitation->revoke();

        AuditTrail::record(
            'Undangan registrasi dicabut',
            $invitation,
            $actor,
            new AuditChange(
                old: ['revoked_at' => null, 'sisa_kuota' => $sisaKuota],
                new: ['revoked_at' => $invitation->fresh()?->revoked_at?->toDateTimeString()],
            ),
        );
    }
}
