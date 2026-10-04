<?php

namespace App\Services\Announcement;

use App\Models\Announcement;
use App\Models\AnnouncementFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Berkas lampiran pengumuman hidup di disk `public`; tabel dan disk selalu bergerak bersama. */
final class AnnouncementFileStore
{
    public const DISK = 'public';

    /** @param  list<UploadedFile>  $files */
    public static function attach(Announcement $announcement, array $files): void
    {
        foreach ($files as $file) {
            $announcement->announcementFiles()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $file->store('announcements', self::DISK),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    /** @param  Collection<int, AnnouncementFile>  $files */
    public static function purge(Collection $files): void
    {
        Storage::disk(self::DISK)->delete($files->pluck('file_path')->all());
    }
}
