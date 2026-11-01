<?php

namespace App\Http\Controllers;

use App\Actions\Announcement\PublishAnnouncement;
use App\Actions\Announcement\RetractAnnouncement;
use App\Actions\Announcement\ReviseAnnouncement;
use App\Data\View\Announcement\AnnouncementFormData;
use App\Http\Requests\Announcement\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\AnnouncementUser;
use App\Queries\Announcement\ManagedAnnouncementList;
use App\Queries\Announcement\VisibleAnnouncements;
use App\Support\Announcement\AnnouncementAudience;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->value();

        return Inertia::render('Announcement/ManageIndexPage', [
            'announcements' => ManagedAnnouncementList::run($search),
            'filters' => ['search' => $search],
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

    public function create()
    {
        return Inertia::render('Announcement/FormPage', [
            'announcement' => null,
            'roleOptions' => AnnouncementAudience::options(),
        ]);
    }

    public function store(AnnouncementRequest $request)
    {
        PublishAnnouncement::run($request->toInput(), $request->user());

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dibuat.');
    }

    // W35 — tanpa layar detail kelola: pengelola membaca isinya di FormPage.
    public function edit(Announcement $announcement)
    {
        return Inertia::render('Announcement/FormPage', [
            'announcement' => AnnouncementFormData::of($announcement),
            'roleOptions' => AnnouncementAudience::options(),
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
