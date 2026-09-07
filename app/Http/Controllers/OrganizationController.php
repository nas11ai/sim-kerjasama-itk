<?php

namespace App\Http\Controllers;

use App\Actions\Identity\DeleteOrganization;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * BR-IAM-08 — penghapusan unit organisasi.
 *
 * Deaktivasi (BR-IAM-14) menyusul di irisan organisasi; keduanya sengaja
 * endpoint terpisah karena ambangnya berbeda: hapus melihat UserProfile apa
 * pun, nonaktif melihat user aktif.
 */
class OrganizationController extends Controller
{
    public function destroy(Organization $organization): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);

        $tercabut = DeleteOrganization::run($organization);

        return back()->with(
            'success',
            $tercabut === 0
                ? 'Unit organisasi dihapus.'
                : "Unit organisasi dihapus. {$tercabut} undangan registrasi ikut tercabut."
        );
    }
}
