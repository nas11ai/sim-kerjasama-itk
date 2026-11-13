<?php

namespace App\Actions\Identity;

use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-18 — memindahkan user antar unit organisasi. **Prasyarat BR-IAM-14**: deaktivasi unit
 * menuntut "pindahkan orangnya lebih dulu", padahal sebelum ini tak ada jalur pemindahan —
 * Operator hanya bisa menonaktifkan orangnya, mengubah keadaan manusia demi merapikan struktur.
 *
 * @method static void run(User $actor, User $user, Organization $tujuan)
 */
class MoveUserToOrganization
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, User $user, Organization $tujuan): void
    {
        if (!$actor->can('organizations.manage')) {
            throw new AuthorizationException('Hanya Operator yang dapat memindahkan pengguna antar unit.');
        }

        $profile = $user->userProfile;

        if ($profile === null) {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna ini belum melengkapi profil, jadi belum punya unit untuk dipindah.',
            ]);
        }

        // Memindahkan ke unit nonaktif memasukkan orang ke tempat yang sudah
        // berhenti menerima siapa pun (BR-IAM-14) — akses form-nya langsung
        // putus di BR-FE-02 tanpa ada yang menjelaskan kenapa.
        if (!$tujuan->is_active) {
            throw ValidationException::withMessages([
                'organization_id' => 'Unit tujuan sudah nonaktif, jadi tidak menerima anggota baru.',
            ]);
        }

        $sebelum = $profile->organization_id;

        $profile->update(['organization_id' => $tujuan->id]);

        // Perpindahan unit mengubah form apa saja yang terlihat orang ini
        // (BR-FE-02). Jejaknya menjawab "kenapa pengajuan saya hilang dari
        // daftar" yang pasti datang belakangan.
        AuditTrail::record(
            'User dipindahkan antar unit',
            $profile->refresh(),
            $actor,
            new AuditChange(
                old: ['organization_id' => $sebelum],
                new: ['organization_id' => $tujuan->id],
            ),
        );
    }
}
