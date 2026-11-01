<?php

namespace App\Support\Announcement;

use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_URIDefinition;
use Illuminate\Support\Str;

/**
 * fe-v2-design §4.7 K6 — server adalah batas kepercayaan; v-html hanya menerima keluaran ini.
 * Disaring saat menulis (Publish/Revise) dan saat membaca, jadi baris lama tidak lolos mentah.
 */
final class AnnouncementHtml
{
    private const ALLOWED = 'p,br,strong,em,u,s,h2,h3,ul,ol,li,blockquote,a[href],img[src|alt]';

    private const BLOCK_TAG = '#</?(?:p|br|h[1-6]|li|ul|ol|blockquote|div)\b[^>]*>#i';

    private const IMG_SRC = '#<img\b[^>]*\bsrc="([^"]*)"#i';

    private static ?HTMLPurifier $purifier = null;

    public static function sanitize(string $html): string
    {
        return self::purifier()->purify($html);
    }

    /** Teks polos untuk daftar, panel login, kutipan, dan cek "isi wajib". */
    public static function plainText(string $html): string
    {
        $spaced = preg_replace(self::BLOCK_TAG, ' ', $html) ?? $html;

        return Str::squish(html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * K6a — path disk gambar sisipan yang dirujuk isi ini.
     *
     * @return list<string>
     */
    public static function inlineImagePaths(string $html): array
    {
        preg_match_all(self::IMG_SRC, $html, $matches);
        $paths = array_filter(array_map(InlineImageSource::pathOf(...), $matches[1]));

        return array_values(array_unique($paths));
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // Tautan selalu ke tab baru dengan rel noopener noreferrer (bawaan TargetBlank).
        $config->set('HTML.TargetBlank', true);
        // Tanpa ini HTMLPurifier mengisi alt kosong dengan nama berkas acak.
        $config->set('Attr.DefaultImageAlt', '');

        $uri = $config->getDefinition('URI', true);
        if ($uri instanceof HTMLPurifier_URIDefinition) {
            $uri->addFilter(new InlineImageUriFilter, $config);
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
