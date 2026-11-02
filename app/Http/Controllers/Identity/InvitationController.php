<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\IssueInvitation;
use App\Actions\Identity\RevokeInvitation;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\IssueInvitationRequest;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Queries\Identity\InvitationList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** BR-IAM-16, ADMIN-08-01 — route-nya dijaga `InvitationPolicy::manage` (W47). */
class InvitationController extends Controller
{
    use ResolvesActor;

    public function index(): Response
    {
        return Inertia::render('Identity/InvitationIndexPage', InvitationList::run());
    }

    public function store(IssueInvitationRequest $request): RedirectResponse
    {
        $invitation = IssueInvitation::run(
            $this->actor($request),
            Organization::findOrFail($request->organizationId()),
            [
                'permissions' => $request->preset()->permissions(),
                'max_uses' => $request->maxUses(),
                'expires_at' => $request->expiresAt(),
            ],
        );

        // W51 — tautan tampil sekali lewat flash native, yang tidak tersimpan di riwayat
        // browser; menyimpannya berarti menyimpan kredensial bagi pembuka layar berikutnya.
        Inertia::flash('issuedInvitationUrl', route('register.invitation.show', $invitation->token));

        return back()->with('success', 'Undangan diterbitkan.');
    }

    public function destroy(Request $request, InvitationToken $invitation): RedirectResponse
    {
        RevokeInvitation::run($this->actor($request), $invitation);

        return back()->with('success', 'Undangan dicabut.');
    }
}
