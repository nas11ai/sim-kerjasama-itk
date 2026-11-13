<?php

namespace App\Http\Controllers;

use App\Actions\FormSubmission\DeactivateUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * BR-IAM-05 dan BR-SM-09 — menonaktifkan user.
 *
 * Deaktivasi tidak menghapus apa pun; yang menghalangi hanyalah tanggung
 * jawab yang belum dialihkan.
 */
class UserDeactivationController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        // Wewenang dijaga route: UserPolicy::deactivate (users.manage, bukan diri sendiri, bukan Super Admin).
        DeactivateUser::run($user);

        return back()->with('success', 'Pengguna dinonaktifkan.');
    }
}
