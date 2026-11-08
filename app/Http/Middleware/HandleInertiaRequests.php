<?php

namespace App\Http\Middleware;

use App\Queries\Announcement\UnreadAnnouncementCount;
use App\Queries\Identity\EffectivePermissionNames;
use App\Queries\Monev\HasMonevSubmission;
use App\Queries\Notification\UnreadNotificationCount;
use App\Queries\ResearchOutput\HasOutputSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $this->bridgeSessionFlash($request);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'permissions' => fn () => EffectivePermissionNames::run($request->user()),
                ] : null,
            ],
            'flash' => [
                // BR-REV-04 — peringatan beban reviewer bukan error: penugasan
                // berhasil, Operator yang menilai kewajaran beban. Dibagikan sebagai
                // flash tersendiri supaya tidak tertukar dengan kegagalan.
                'workloadWarnings' => fn () => $request->session()->get('workloadWarnings'),
            ],
            'navigation' => fn () => $request->user()
                ? [
                    // W142 — "Luaran Penelitian" tampil bila daftarnya berisi.
                    'hasOutputSubmission' => HasOutputSubmission::run($request->user()),
                    // W128 — "Daftar Monev" tampil bila daftarnya berisi.
                    'hasMonevSubmission' => HasMonevSubmission::run($request->user()),
                    // W32 — badge sidebar "Pengumuman" (fe-v2-design §4.7).
                    'unreadAnnouncements' => UnreadAnnouncementCount::run($request->user()),
                    // W38/W41 — badge sidebar "Notifikasi", ber-cache (BR-NOT-05).
                    'unreadNotifications' => UnreadNotificationCount::run($request->user()),
                ]
                : null,
        ]);
    }

    /**
     * W162 — pesan sukses/galat dari `redirect()->with(...)` diteruskan sebagai flash native
     * Inertia (toast). Flash native tidak disimpan di riwayat, jadi Back tidak memutarnya ulang.
     */
    private function bridgeSessionFlash(Request $request): void
    {
        $flash = array_filter(array_filter($request->session()->only(['message', 'error', 'success']), is_string(...)));

        if ($flash !== []) {
            Inertia::flash($flash);
        }
    }
}
