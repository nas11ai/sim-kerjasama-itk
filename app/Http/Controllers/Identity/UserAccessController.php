<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\GrantDirectPermission;
use App\Actions\Identity\RevokeDirectPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\DirectPermissionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * BR-IAM-09, BR-IAM-11 — hak akses dan penunjukan reviewer atas satu user.
 *
 * Hak akses langsung menambah, bukan menggantikan yang dari role (BR-IAM-09,
 * bawaan Spatie) — mencabut satu sumber tak menyentuh yang lain; jangan diratakan.
 */
class UserAccessController extends Controller
{
    public function appointReviewer(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'reviewer_type' => ['required', Rule::in([AppointReviewer::TYPE_INTERNAL, AppointReviewer::TYPE_EXTERNAL])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        AppointReviewer::run(
            $user,
            $validated['reviewer_type'],
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        );

        return back()->with('success', 'Reviewer ditunjuk.');
    }

    public function grantPermission(DirectPermissionRequest $request, User $user): RedirectResponse
    {
        GrantDirectPermission::run($this->actor($request), $user, $request->permissionName());

        return back()->with('success', 'Hak akses ditambahkan.');
    }

    public function revokePermission(DirectPermissionRequest $request, User $user): RedirectResponse
    {
        RevokeDirectPermission::run($this->actor($request), $user, $request->permissionName());

        return back()->with('success', 'Hak akses langsung dicabut.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    // Penunjukan reviewer pindah ke reviewers.assign di I1-2 (W47); sampai itu tetap users.manage.
    private function authorizeManage(): void
    {
        $actor = Auth::user();

        abort_unless($actor !== null && $actor->can('users.manage'), 403);
    }
}
