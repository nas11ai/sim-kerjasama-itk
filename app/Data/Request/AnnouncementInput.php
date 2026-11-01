<?php

namespace App\Data\Request;

use Illuminate\Http\UploadedFile;

/**
 * Masukan satu pengumuman: isi, target peran (fe-v2-design §4.7), berkas baru, dan id
 * lampiran yang dilepas saat revisi. `content` masih mentah; Action yang menyaringnya.
 */
final class AnnouncementInput
{
    /**
     * @param  list<string>  $targetRoles
     * @param  list<UploadedFile>  $files
     * @param  list<int>  $deletedFileIds
     */
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly string $type,
        public readonly ?string $expiredAt,
        public readonly array $targetRoles = [],
        public readonly array $files = [],
        public readonly array $deletedFileIds = [],
    ) {}
}
