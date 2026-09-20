<?php

namespace App\Actions\Identity;

use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-14 — menonaktifkan unit organisasi. Menyatukannya dengan penghapusan
 * memaksa memilih salah satu kerugian: fakultas yang pernah berisi orang tak pernah
 * bisa dipensiunkan, atau unit yang salah ketik saat dibuat cuma bisa disembunyikan.
 *
 * @method static int run(Organization $organization)
 */
class DeactivateOrganization
{
    use AsAction;

    /**
     * Ambangnya **user aktif** di seluruh subtree — berbeda dari penghapusan (BR-IAM-08) yang
     * melihat UserProfile apa pun: unit yang seluruh penghuninya sudah nonaktif boleh
     * dipensiunkan, tapi belum boleh dihapus karena jejaknya masih dibaca.
     *
     * @return int jumlah undangan yang ikut tidak berlaku
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization): int
    {
        $subtree = Organization::subtreeIds($organization->id);

        $activeUsers = User::query()
            ->where('is_active', true)
            ->whereIn(
                'id',
                UserProfile::query()->whereIn('organization_id', $subtree)->select('user_id')
            )
            ->count();

        if ($activeUsers > 0) {
            throw ValidationException::withMessages([
                'organization_id' => "Masih ada {$activeUsers} user aktif di unit ini atau turunannya. "
                    .'Pindahkan atau nonaktifkan orangnya lebih dulu.',
            ]);
        }

        $tercatat = DB::transaction(function () use ($subtree): int {
            // Tokennya tidak dicabut: organisasi nonaktif sudah membuat `isValid()`
            // menolaknya (BR-IAM-06). Kalau unit ini diaktifkan kembali, undangan yang belum
            // lewat masa berlakunya ikut hidup lagi — tidak ada keputusan untuk mencabutnya.
            $terdampak = InvitationToken::query()
                ->whereIn('organization_id', $subtree)
                ->whereNull('revoked_at')
                ->count();

            // Deaktivasi **tidak menghapus apa pun**. Yang berubah: unit ini
            // berhenti ditawarkan, rantai leluhurnya putus di access control
            // (BR-FE-02), dan undangannya tidak lagi berlaku (BR-IAM-06).
            Organization::query()->whereIn('id', $subtree)->update(['is_active' => false]);

            return $terdampak;
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Organisasi dinonaktifkan', $organization);

        return $tercatat;
    }
}
