<?php

namespace App\Http\Controllers;

use App\Actions\FormSubmission\DeactivateUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

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
        $actor = Auth::user();

        abort_unless($actor !== null && $actor->can('users.manage'), 403);

        DeactivateUser::run($user);

        return back()->with('success', 'User dinonaktifkan.');
    }
}
