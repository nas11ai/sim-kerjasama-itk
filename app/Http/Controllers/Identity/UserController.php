<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\ChangeUserRoles;
use App\Actions\Identity\EndUserSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangeUserRolesRequest;
use App\Models\InvitationToken;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\UserDetail;
use App\Queries\Identity\UserDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar dan detail user (OPERATOR-08-01, 08-03). Route dijaga UserPolicy (W47).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'role' => $request->string('role')->value(),
            'account' => $request->string('account')->value(),
            'verification' => $request->string('verification')->value(),
            'presence' => $request->string('presence')->value(),
        ];

        return Inertia::render('Identity/UserIndexPage', [
            'filters' => $filters,
            ...UserDirectory::run($filters),
            'awaitingVerification' => UserProfile::query()->awaitingVerification()->count(),
            'can' => [
                'verify' => $viewer->can('users.verify'),
                'manageInvitations' => $viewer->can('manage', InvitationToken::class),
            ],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return Inertia::render('Identity/UserShowPage', UserDetail::run($viewer, $user, $request->string('tab')->value()));
    }

    public function updateRoles(ChangeUserRolesRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        ChangeUserRoles::run($actor, $user, $request->roleNames());

        return back()->with('success', 'Peran user diperbarui.');
    }

    // BR-IAM-21 — wewenang dijaga route (UserPolicy::endSessions).
    public function endSessions(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        EndUserSessions::run($actor, $user);

        return back()->with('success', 'Seluruh sesi user diakhiri.');
    }
}
