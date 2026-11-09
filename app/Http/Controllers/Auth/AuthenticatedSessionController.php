<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SessionEndReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Queries\Announcement\PublicAnnouncementPanel;
use App\Queries\Identity\PostLoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/LoginPage', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            // W170 — tab yang menerima `sessions.ended` mendarat di sini dengan ?sesi=<alasan>.
            'notice' => SessionEndReason::tryFrom($request->string('sesi')->toString())?->notice(),
            // W6 — panel pengumuman publik di samping form login (fe-v2-design §4.7).
            'announcements' => PublicAnnouncementPanel::run(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        return redirect()->intended(route(PostLoginDestination::run($user)));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
