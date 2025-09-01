<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $announcements = Announcement::with(['announcementFiles'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Announcements/Index', [
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
            'announcement' => new Announcement(),
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
            'files.*' => 'file|mimes:jpg,png,pdf|max:2048',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = auth()->user();

            $announcement = Announcement::create([
                'title' => $validated['title'],
                // 'slug' => Str::slug($validated['title']), ntar kalo dipake
                'content' => $validated['content'],
                'expired_at' => $validated['expired_at'] ?? null,
                'type' => $validated['type'],
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => $user->id,
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('announcements');

                    AnnouncementFile::create([
                        'announcement_id' => $announcement->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('admin.announcements.index')
                ->with('success', 'Announcement created successfully.');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement)
    {
        $announcement->load('announcementFiles');
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
            'type' => 'required|enum:in:public,private',
            'expired_at' => 'nullable|date',
            'files.*' => 'file|mimes:jpg,png,pdf|max:2048',
        ]);

        DB::transaction(function () use ($validated, $request, $announcement) {
            $announcement->update([
                'title' => $validated['title'],
                // 'slug' => Str::slug($validated['title']), ntar kalo dipake
                'content' => $validated['content'],
                'type' => $validated['type'],
                'expired_at' => $validated['expired_at'] ?? null,
                'updated_at' => now(),
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('announcements');

                    AnnouncementFile::create([
                        'announcement_id' => $announcement->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }
            return redirect()->route('admin.announcements.index')
                ->with('success', 'Announcement updated successfully.');
        });
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
            ->with('success', 'Announcement deleted successfully.');
    }

    public function detail(Announcement $announcement)
    {
        $announcement->load('announcementFiles');
        return Inertia::render('AnnouncementDetail', [
            'announcement' => $announcement,
        ]);
    }
}
