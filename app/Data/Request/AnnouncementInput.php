<?php

namespace App\Data\Request;

use Illuminate\Http\UploadedFile;

/** Masukan satu pengumuman: isi, berkas baru, dan id lampiran yang dilepas saat revisi. */
final class AnnouncementInput
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<int>  $deletedFileIds
     */
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly string $type,
        public readonly ?string $expiredAt,
        public readonly array $files = [],
        public readonly array $deletedFileIds = [],
    ) {}
}
