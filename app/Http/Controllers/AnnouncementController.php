<?php

namespace App\Http\Controllers;

use App\Actions\Announcement\PublishAnnouncement;
use App\Actions\Announcement\RetractAnnouncement;
use App\Actions\Announcement\ReviseAnnouncement;
use App\Data\View\Announcement\AnnouncementFormData;
use App\Http\Requests\Announcement\AnnouncementRequest;
use App\Models\Announcement;
use App\Queries\Announcement\ManagedAnnouncementList;
use App\Queries\Announcement\ReaderAnnouncementDetail;
use App\Queries\Announcement\ReaderAnnouncementList;
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
        return Inertia::render('Announcement/IndexPage', [
            'announcements' => ReaderAnnouncementList::run($request->user()),
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
        $detail = ReaderAnnouncementDetail::run($announcement, $request->user());
        abort_if($detail === null, 404);

        return Inertia::render('Announcement/ShowPage', ['announcement' => $detail]);
    }

    /**
     * fe-v2-design §4.7 — penanda baca bukan state domain dan tidak diaudit (preseden
     * BR-NOT-04, `NotificationController::read`). Hanya yang terlihat boleh ditandai.
     */
    public function markAsRead(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        abort_unless(VisibleAnnouncements::run($user)->whereKey($announcement->id)->exists(), 404);

        $announcement->announcementReader()->syncWithoutDetaching([$user->id]);

        return back();
    }
}
