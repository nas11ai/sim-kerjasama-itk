<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\AnnouncementUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        return Inertia::render('Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function userIndex()
    {
        $announcements = Announcement::with(['announcementFiles'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('User/Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Announcements/Create', [
            'announcementFiles' => AnnouncementFile::all(),
            'announcement' => new Announcement,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:public,private',
            'expired_at' => 'nullable|date',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,png,pdf|max:2048',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = auth()->user();

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'expired_at' => $validated['expired_at'] ?? null,
                'type' => $validated['type'],
                'created_at' => Carbon::now('Asia/Makassar'),
                'updated_at' => Carbon::now('Asia/Makassar'),
                'created_by' => $user->id,
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('announcements', 'public');

                    AnnouncementFile::create([
                        'announcement_id' => $announcement->id,
                        'file_name' => $file->getClientOriginalName(),
                        // 'file_path' => asset('storage/' . $path),
                        'file_path' => $path, // buat prod
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }
        });

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement)
    {
        $announcement->load('announcementFiles', 'announcementCreator');

        return Inertia::render('Announcements/Show', [
            'announcement' => $announcement,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Announcement $announcement)
    {
        $announcement->load('announcementFiles');

        return Inertia::render('Announcements/Edit', [
            'announcement' => $announcement,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:public,private',
            'expired_at' => 'nullable|date',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,png,pdf|max:2048',
        ]);

        DB::transaction(function () use ($validated, $request, $announcement) {
            if ($request->has('deleted_files') && is_array($request->deleted_files)) {
                foreach ($request->deleted_files as $fileId) {
                    $file = AnnouncementFile::find($fileId);
                    if ($file) {
                        Storage::disk('public')->delete($file->file_path);
                        $file->delete();
                    }
                }
            }

            $announcement->update([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'type' => $validated['type'],
                'expired_at' => $validated['expired_at'] ?? null,
                'updated_at' => now(),
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('announcements', 'public');

                    AnnouncementFile::create([
                        'announcement_id' => $announcement->id,
                        'file_name' => $file->getClientOriginalName(),
                        // 'file_path' => asset('storage/' . $path),
                        'file_path' => $path, // buat prod
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }
        });

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announcement $announcement)
    {
        foreach ($announcement->announcementFiles as $file) {
            Storage::delete($file->file_path);
            $file->delete();
        }
        $announcement->delete();

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function detail(Announcement $announcement)
    {
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
