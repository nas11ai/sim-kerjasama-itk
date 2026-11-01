<?php

namespace App\Actions\Announcement;

use App\Models\User;
use App\Services\Announcement\AnnouncementFileStore;
use App\Support\Announcement\InlineImageEncoder;
use App\Support\Announcement\InlineImageSource;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * fe-v2-design §4.7 K6a — menyimpan satu gambar sisipan pengumuman yang sudah disandi ulang,
 * bernama acak (nama klien tidak dipakai), lalu mengembalikan `src` relatifnya.
 *
 * @method static string run(UploadedFile $image, User $actor)
 */
class UploadAnnouncementImage
{
    use AsAction;

    public const AUDIT_DESCRIPTION = 'Gambar pengumuman diunggah';

    public function handle(UploadedFile $image, User $actor): string
    {
        // Mime dari isi berkas (finfo), bukan ekstensi atau header kiriman klien.
        $mime = $image->getMimeType() ?? '';
        $extension = InlineImageEncoder::EXTENSIONS[$mime] ?? null;
        $encoded = $extension === null ? null : InlineImageEncoder::reencode($image->getContent(), $mime);

        if ($extension === null || $encoded === null) {
            throw ValidationException::withMessages(['image' => 'Gambar tidak dapat diproses.']);
        }

        $path = InlineImageSource::DIRECTORY.'/'.Str::random(40).'.'.$extension;
        Storage::disk(AnnouncementFileStore::DISK)->put($path, $encoded);

        // Unggahan belum terikat pengumuman, jadi subjek auditnya pengunggah.
        AuditTrail::record(self::AUDIT_DESCRIPTION, $actor, $actor, new AuditChange(new: ['path' => $path]));

        return InlineImageSource::urlOf($path);
    }
}
