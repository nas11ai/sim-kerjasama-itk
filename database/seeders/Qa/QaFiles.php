<?php

namespace Database\Seeders\Qa;

use Illuminate\Http\UploadedFile;

/**
 * Berkas kecil yang diunggah seeder lewat jalur unggah biasa. Disalin ke berkas sementara setiap kali,
 * karena penyimpanan boleh memindahkan sumbernya; tidak butuh GD di mesin yang menjalankan seeder.
 */
final class QaFiles
{
    public static function photo(): UploadedFile
    {
        return self::copy('foto-profil.jpg', 'foto-profil.jpg', 'image/jpeg');
    }

    public static function pdf(string $name): UploadedFile
    {
        return self::copy('dokumen.pdf', $name, 'application/pdf');
    }

    private static function copy(string $source, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'qa-seed-');
        copy(__DIR__.'/files/'.$source, $path);

        // `test: true` = berkas ini tidak datang dari unggahan HTTP, jadi `is_uploaded_file()` tidak berlaku.
        return new UploadedFile($path, $name, $mime, null, true);
    }
}
