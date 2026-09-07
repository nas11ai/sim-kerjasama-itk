<?php

namespace App\Actions\Identity;

use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-08, BR-IAM-06 — menghapus organisasi.
 *
 * Ambangnya **UserProfile apa pun** di seluruh subtree, aktif maupun tidak.
 * Lebih ketat daripada deaktivasi (BR-IAM-14), dan sengaja: jejak siapa yang
 * pernah bekerja di unit ini tidak boleh ikut hilang. Operasi sehari-harinya
 * memang deaktivasi; penghapusan hanya untuk unit yang belum pernah dipakai.
 *
 * Undangan yang belum terpakai **tidak menghalangi** — ia dicabut (register
 * #18). Token basi tidak boleh menyandera reorganisasi selamanya, tapi token
 * yang menggantung ke organisasi mati juga tidak boleh hidup. Jumlah yang
 * akan tercabut dikembalikan supaya Operator bisa diberi tahu sebelum
 * mengonfirmasi.
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

        $profiles = UserProfile::query()->whereIn('organization_id', $subtree)->count();

        if ($profiles > 0) {
            throw ValidationException::withMessages([
                'organization_id' => "Masih ada {$profiles} profil user di unit ini atau turunannya. "
                    .'Nonaktifkan unit ini kalau yang Anda maksud adalah memensiunkannya.',
            ]);
        }

        return DB::transaction(function () use ($subtree): int {
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
    }
}
