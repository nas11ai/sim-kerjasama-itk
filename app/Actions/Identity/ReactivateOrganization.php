<?php

namespace App\Actions\Identity;

use App\Models\Organization;
use App\Queries\Identity\RevivableInvitations;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-22 (W119) — mengaktifkan kembali satu unit nonaktif. Turunannya tetap nonaktif: tidak
 * tercatat mana yang ikut nonaktif karena induknya dan mana yang dipensiunkan sendiri.
 *
 * @method static int run(Organization $organization)
 */
class ReactivateOrganization
{
    use AsAction;

    /**
     * @return int jumlah undangan yang berlaku lagi
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization): int
    {
        // Unit yang sudah aktif: tak ada keputusan, jadi tak ada jejak (pola IAM-REACT-05).
        if ($organization->is_active) {
            return 0;
        }

        // Unit aktif di bawah induk nonaktif tetap terputus dari rantai leluhur BR-FE-02.
        if ($organization->parent !== null && !$organization->parent->is_active) {
            throw ValidationException::withMessages([
                'organization_id' => 'Induk unit ini masih nonaktif. Aktifkan induknya lebih dulu.',
            ]);
        }

        $revived = RevivableInvitations::run($organization);

        // Lewat model, bukan update massal: event `saved` membuang cache subtree dan leluhur.
        $organization->update(['is_active' => true]);

        AuditTrail::record(
            'Organisasi diaktifkan kembali',
            $organization,
            change: new AuditChange(old: ['is_active' => false], new: ['is_active' => true]),
        );

        return $revived;
    }
}
