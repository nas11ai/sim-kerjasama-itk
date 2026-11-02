<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\GrantDirectPermission;
use App\Actions\Identity\RevokeDirectPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\DirectPermissionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BR-IAM-09 — hak akses langsung atas satu user; penunjukan reviewer di ReviewerManagementController.
 *
 * Hak akses langsung menambah, bukan menggantikan yang dari role (BR-IAM-09,
 * bawaan Spatie) — mencabut satu sumber tak menyentuh yang lain; jangan diratakan.
 */
class UserAccessController extends Controller
{
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
}
