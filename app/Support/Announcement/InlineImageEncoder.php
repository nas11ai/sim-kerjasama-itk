<?php

namespace App\Support\Announcement;

use GdImage;

/**
 * fe-v2-design §4.7 K6a — gambar unggahan disandi ulang lewat GD sehingga hanya piksel yang
 * tersisa: metadata EXIF/GPS dan muatan poliglot di luar data gambar ikut terbuang.
 */
final class InlineImageEncoder
{
    /** @var array<string, string> mime terdeteksi → ekstensi simpan */
    public const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    private const JPEG_QUALITY = 85;

    /** Bytes hasil sandi ulang, atau null bila GD tidak bisa membaca gambarnya. */
    public static function reencode(string $bytes, string $mime): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if (!$image instanceof GdImage) {
            return null;
        }

        ob_start();
        $written = $mime === 'image/png' ? self::png($image) : imagejpeg($image, null, self::JPEG_QUALITY);
        $encoded = ob_get_clean();

        return $written && is_string($encoded) && $encoded !== '' ? $encoded : null;
    }

    private static function png(GdImage $image): bool
    {
        imagesavealpha($image, true);

        return imagepng($image);
    }
}
