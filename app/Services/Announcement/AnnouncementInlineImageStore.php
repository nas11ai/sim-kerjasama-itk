<?php

namespace App\Services\Announcement;

use App\Models\Announcement;
use Illuminate\Support\Facades\Storage;

/**
 * fe-v2-design §4.7 K6a — berkas gambar sisipan dihapus begitu tak lagi dirujuk: yang dibuang
 * revisi, atau milik pengumuman yang ditarik. Yang masih dirujuk pengumuman lain dibiarkan.
 */
final class AnnouncementInlineImageStore
{
    /** @param  list<string>  $paths */
    public static function purgeUnreferenced(array $paths, int $exceptId): void
    {
        $orphans = array_filter($paths, static fn (string $path): bool => !Announcement::query()
            ->whereKeyNot($exceptId)
            ->whereLike('content', '%'.$path.'%')
            ->exists());

        Storage::disk(AnnouncementFileStore::DISK)->delete(array_values($orphans));
    }
}
