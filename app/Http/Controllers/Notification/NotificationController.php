<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-NOT-01, BR-NOT-04 — daftar notifikasi in-app.
 *
 * Membuka daftar **tidak** menandai apa pun sebagai dibaca. Kalau ia
 * menandai, penghitung "belum dibaca" langsung nol pada kunjungan pertama
 * dan notifikasi yang belum sempat dilihat ikut terkubur — persis kebalikan
 * dari gunanya penghitung itu.
 *
 * Notifikasi tersimpan permanen dan hanya hilang kalau pemiliknya
 * menghapusnya sendiri (BR-NOT-01). Tidak ada pembersihan otomatis: yang
 * dianggap sudah tidak perlu oleh sistem sering justru yang sedang dicari
 * orangnya.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

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

        $url = is_string($entry->data['action_url'] ?? null) ? $entry->data['action_url'] : null;

        return $url !== null ? redirect($url) : back();
    }

    /**
     * BR-NOT-01 — satu-satunya cara sebuah notifikasi hilang.
     */
    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->own($request, $notification)->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }

    private function own(Request $request, string $id): DatabaseNotification
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $entry = $user->notifications()->whereKey($id)->first();

        // 404, bukan 403: memberitahu bahwa notifikasi orang lain "ada tapi
        // bukan milikmu" sudah membocorkan keberadaannya.
        abort_if($entry === null, 404);

        return $entry;
    }
}
