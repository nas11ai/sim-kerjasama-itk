<?php

namespace App\Http\Controllers;

use App\Queries\Announcement\PublicAnnouncementPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * K7 (pemilik 2026-11-13) — `/` untuk tamu: halaman sambutan dengan pengumuman publik. User yang
 * sudah masuk langsung ke Beranda. Membalik W157.
 */
class WelcomeController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Welcome/WelcomePage', [
            'canRegister' => Route::has('register'),
            'announcements' => PublicAnnouncementPanel::run(),
        ]);
    }
}
