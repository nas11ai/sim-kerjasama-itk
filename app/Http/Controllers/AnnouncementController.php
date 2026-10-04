<?php

namespace App\Http\Controllers;

use App\Actions\Announcement\PublishAnnouncement;
use App\Actions\Announcement\RetractAnnouncement;
use App\Actions\Announcement\ReviseAnnouncement;
use App\Http\Requests\Announcement\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\AnnouncementUser;
use App\Queries\Announcement\VisibleAnnouncements;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $announcements = Announcement::with(['announcementFiles', 'announcementCreator'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Announcements/IndexPage', [
            'announcements' => $announcements,
        ]);
    }

    public function userIndex(Request $request)
    {
        $user = $request->user();

        $announcements = VisibleAnnouncements::run($user)
            ->with('announcementFiles')
            ->withExists(['announcementReader as is_read' => static fn ($query) => $query->where('users.id', $user?->id)])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('User/Announcements/IndexPage', [
            'announcements' => $announcements,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Tanpa data awal: dulu mengirim seluruh tabel AnnouncementFile dan sebuah
        // Announcement kosong yang tak dibaca layar — satu tabel penuh tiap halaman dibuka.
        return Inertia::render('Announcements/CreatePage');
    }

    public function store(AnnouncementRequest $request)
    {
        PublishAnnouncement::run($request->toInput(), $request->user());

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement)
    {
        $announcement->load('announcementFiles', 'announcementCreator');

        return Inertia::render('Announcements/ShowPage', [
            'announcement' => $announcement,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Announcement $announcement)
    {
        $announcement->load('announcementFiles');

        return Inertia::render('Announcements/EditPage', [
            'announcement' => $announcement,
        ]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement)
    {
        ReviseAnnouncement::run($announcement, $request->toInput(), $request->user());

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        RetractAnnouncement::run($announcement, $request->user());

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function detail(Request $request, Announcement $announcement)
    {
        // Pengumuman yang tak terlihat oleh penanya dijawab 404, bukan 403:
        // keberadaannya tidak dibocorkan ke tamu yang menebak id.
        abort_unless(
            VisibleAnnouncements::run($request->user())->whereKey($announcement->id)->exists(),
            404,
        );

        $announcement->load('announcementFiles', 'announcementCreator');

        return Inertia::render('AnnouncementDetail', [
            'announcement' => $announcement,
        ]);
    }

    public function markAsRead(Announcement $announcement)
    {
        $user = auth()->user();

        $reader = AnnouncementUser::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            [
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Pengumuman berhasil ditandai sebagai dibaca.',
            'data' => $reader,
        ]);
    }
}
