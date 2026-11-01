<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $user = $request->user();

        abort_if($user === null, 403);

        // Membuka daftar tidak menandai apa pun dibaca: kalau menandai,
        // penghitung "belum dibaca" langsung nol di kunjungan pertama, dan
        // notifikasi yang belum sempat dilihat ikut terkubur — kebalikan gunanya.
        return Inertia::render('Notifications/IndexPage', [
            'notifications' => $user->notifications()
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'type' => class_basename($notification->type),
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ]),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * BR-NOT-04 — `read_at` diisi saat notifikasinya diklik.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $entry = $this->own($request, $notification);

        $entry->markAsRead();
        UnreadNotificationCount::forget($this->viewer($request));

        $url = is_string($entry->data['action_url'] ?? null) ? $entry->data['action_url'] : null;

        return $url !== null ? redirect($url) : back();
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
