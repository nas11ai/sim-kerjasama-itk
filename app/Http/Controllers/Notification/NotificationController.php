<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Notification\NotificationDestination;
use App\Queries\Notification\NotificationInbox;
use App\Queries\Notification\UnreadNotificationCount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-NOT-01, BR-NOT-04 — daftar notifikasi in-app.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        // BR-NOT-04 — membuka daftar tidak menandai apa pun dibaca.
        return Inertia::render('Notification/IndexPage', NotificationInbox::run(
            $this->viewer($request),
            $request->string('filter')->toString(),
        ));
    }

    /**
     * BR-NOT-04 — `read_at` diisi saat notifikasinya diklik.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $entry = $this->own($request, $notification);

        $entry->markAsRead();
        UnreadNotificationCount::forget($this->viewer($request));

        // W39, BR-SM-07 — tujuan dihitung sekarang; yang tak lagi boleh dibuka kembali ke daftar.
        return redirect(NotificationDestination::run($entry, $this->viewer($request)) ?? route('notifications.index'));
    }

    /**
     * BR-NOT-04, W37 — "Tandai Semua Dibaca" adalah tindakan eksplisit, bukan
     * efek membuka halaman. Notifikasinya tetap tersimpan (BR-NOT-01).
     */
    public function readAll(Request $request): RedirectResponse
    {
        $user = $this->viewer($request);

        $user->unreadNotifications()->update(['read_at' => now()]);
        UnreadNotificationCount::forget($user);

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }

    /**
     * BR-NOT-01 — satu-satunya cara sebuah notifikasi hilang. Tersimpan permanen,
     * tak ada pembersihan otomatis: yang dianggap tak perlu oleh sistem sering
     * justru yang sedang dicari orangnya.
     */
    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $entry = $this->own($request, $notification);

        $entry->delete();
        UnreadNotificationCount::forget($this->viewer($request));

        return back()->with('success', 'Notifikasi dihapus.');
    }

    private function own(Request $request, string $id): DatabaseNotification
    {
        $entry = $this->viewer($request)->notifications()->whereKey($id)->first();

        // 404, bukan 403: memberitahu bahwa notifikasi orang lain "ada tapi
        // bukan milikmu" sudah membocorkan keberadaannya.
        abort_if($entry === null, 404);

        return $entry;
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return $user;
    }
}
