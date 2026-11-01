<?php

namespace App\Support\Announcement;

/**
 * fe-v2-design §4.7 K6a — satu-satunya bentuk `src` gambar sisipan yang sah: path relatif
 * ke berkas bernama acak di disk `public`. Relatif supaya tetap sah bila APP_URL berganti.
 */
final class InlineImageSource
{
    public const DIRECTORY = 'announcements/inline';

    public const URL_PREFIX = '/storage/'.self::DIRECTORY.'/';

    /** Nama acak 40 karakter alfanumerik dari UploadAnnouncementImage; tanpa `..` atau kueri. */
    private const PATTERN = '#^/storage/announcements/inline/([A-Za-z0-9]{40}\.(?:jpg|png))$#';

    public static function urlOf(string $path): string
    {
        return '/storage/'.$path;
    }

    /** Path disk untuk `src` yang sah, atau null bila `src` bukan gambar sisipan milik sendiri. */
    public static function pathOf(string $src): ?string
    {
        if (preg_match(self::PATTERN, $src, $match) !== 1) {
            return null;
        }

        return self::DIRECTORY.'/'.$match[1];
    }
}
