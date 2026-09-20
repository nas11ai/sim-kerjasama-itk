<?php

namespace App\Actions\Identity;

use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\UserProfile;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-08, BR-IAM-06 — menghapus organisasi.
 *
 * @method static int run(Organization $organization)
 */
class DeleteOrganization
{
    use AsAction;

    /**
     * @return int jumlah undangan yang ikut tercabut
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization): int
    {
        $subtree = Organization::subtreeIds($organization->id);

        // Ambangnya **UserProfile apa pun** di seluruh subtree — lebih ketat daripada deaktivasi
        // (BR-IAM-14), dan sengaja: jejak siapa yang pernah bekerja di unit ini tidak boleh
        // hilang. Sehari-harinya deaktivasi; penghapusan untuk unit yang belum terpakai.
        $profiles = UserProfile::query()->whereIn('organization_id', $subtree)->count();

        if ($profiles > 0) {
            throw ValidationException::withMessages([
                'organization_id' => "Masih ada {$profiles} profil user di unit ini atau turunannya. "
                    .'Nonaktifkan unit ini kalau yang Anda maksud adalah memensiunkannya.',
            ]);
        }

        $tercatat = DB::transaction(function () use ($subtree): int {
            // Undangan yang belum terpakai **tidak menghalangi**, ia dicabut (register #18): token
            // basi tidak boleh menyandera reorganisasi, tapi token yang menggantung ke organisasi
            // mati juga tidak boleh hidup. Jumlahnya dikembalikan untuk konfirmasi Operator.
            $tokens = InvitationToken::query()
                ->whereIn('organization_id', $subtree)
                ->whereNull('revoked_at')
                ->get();

            foreach ($tokens as $token) {
                $token->revoke();
            }

            Organization::query()->whereIn('id', $subtree)->delete();

            return $tokens->count();
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Organisasi dihapus', $organization);

        return $tercatat;
    }
}
