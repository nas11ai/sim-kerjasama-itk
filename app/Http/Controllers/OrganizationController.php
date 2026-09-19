<?php

namespace App\Http\Controllers;

use App\Actions\Identity\DeactivateOrganization;
use App\Actions\Identity\DeleteOrganization;
use App\Actions\Identity\MoveUserToOrganization;
use App\Actions\Identity\SaveOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    /**
     * BR-IAM-14 — operasi sehari-harinya. Ambangnya user **aktif**, bukan
     * profil apa pun; endpoint terpisah dari destroy justru supaya perbedaan
     * itu tidak kabur.
     */
    public function deactivate(Organization $organization): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);

        $terdampak = DeactivateOrganization::run($organization);

        return back()->with(
            'success',
            $terdampak === 0
                ? 'Unit organisasi dinonaktifkan.'
                : "Unit organisasi dinonaktifkan. {$terdampak} undangan registrasi ikut tidak berlaku."
        );
    }

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

    /**
     * BR-IAM-17 — membuat unit baru, akar maupun sub-unit.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = Auth::user();

        abort_unless($actor !== null && $actor->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string|max:64',
            'parent_id' => 'nullable|exists:organizations,id',
        ]);

        SaveOrganization::run($actor, $validated);

        return back()->with('success', 'Unit organisasi dibuat.');
    }

    /**
     * BR-IAM-17 — mengganti nama, tipe, atau induk sebuah unit.
     */
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $actor = Auth::user();

        abort_unless($actor !== null && $actor->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'type' => 'sometimes|string|max:64',
            'parent_id' => 'sometimes|nullable|exists:organizations,id',
        ]);

        SaveOrganization::run($actor, $validated, $organization);

        return back()->with('success', 'Unit organisasi diperbarui.');
    }

    /**
     * BR-IAM-18 — memindahkan user antar unit; prasyarat deaktivasi BR-IAM-14.
     */
    public function moveUser(Request $request, Organization $organization): RedirectResponse
    {
        $actor = Auth::user();

        abort_unless($actor !== null && $actor->can('users.manage'), 403);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        MoveUserToOrganization::run($actor, User::findOrFail($validated['user_id']), $organization);

        return back()->with('success', 'User dipindahkan.');
    }
}
