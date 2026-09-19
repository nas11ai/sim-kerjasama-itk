<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\IssueInvitation;
use App\Actions\Identity\RevokeInvitation;
use App\Http\Controllers\Controller;
use App\Models\InvitationToken;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-IAM-16 — penerbitan dan pencabutan undangan registrasi (ADMIN-08-01).
 */
class InvitationController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);

        return Inertia::render('Identity/InvitationsPage', [
            // Token-nya sendiri tidak ikut dikirim ke daftar: yang perlu
            // terlihat adalah keadaan undangan, bukan kredensialnya. Tautannya
            // diberikan sekali saat diterbitkan.
            'invitations' => InvitationToken::query()
                ->with('organization:id,name')
                ->latest('id')
                ->get()
                ->map(fn (InvitationToken $invitation): array => [
                    'id' => $invitation->id,
                    'organization' => $invitation->organization?->name,
                    'permissions' => $invitation->permissions,
                    'used_count' => $invitation->used_count,
                    'max_uses' => $invitation->max_uses,
                    'expires_at' => $invitation->expires_at?->toDateTimeString(),
                    'revoked_at' => $invitation->revoked_at?->toDateTimeString(),
                    'is_valid' => $invitation->isValid(),
                ]),
            'organizations' => Organization::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'permissions' => 'array',
            'permissions.*' => 'string',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $invitation = IssueInvitation::run(
            $user,
            Organization::findOrFail($validated['organization_id']),
            [
                'permissions' => $validated['permissions'] ?? [],
                'max_uses' => $validated['max_uses'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
            ],
        );

        // Tautannya muncul sekali, di sini. Menyimpannya untuk ditampilkan
        // ulang berarti menyimpan kredensial yang bisa dibaca siapa pun yang
        // membuka layar itu belakangan.
        return back()->with('success', 'Undangan diterbitkan.')
            ->with('invitation_url', route('register.invitation.show', $invitation->token));
    }

    public function destroy(InvitationToken $invitation): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);

        RevokeInvitation::run($user, $invitation);

        return back()->with('success', 'Undangan dicabut.');
    }
}
