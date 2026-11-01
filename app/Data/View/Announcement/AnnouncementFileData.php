<?php

namespace App\Data\View\Announcement;

use App\Models\AnnouncementFile;
use App\Services\Announcement\AnnouncementFileStore;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\Data;

/** Satu lampiran pengumuman; `url` publik karena lampiran tetap di disk `public` (W15). */
final class AnnouncementFileData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        public int $size,
    ) {}

    public static function of(AnnouncementFile $file): self
    {
        return new self(
            id: $file->id,
            name: $file->file_name,
            url: Storage::disk(AnnouncementFileStore::DISK)->url($file->file_path),
            size: $file->file_size,
        );
    }
}
