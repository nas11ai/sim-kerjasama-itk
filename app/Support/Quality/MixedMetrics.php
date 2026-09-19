<?php

namespace App\Support\Quality;

use PhpToken;

/**
 * Menghitung tipe serba-boleh yang ditulis di tanda tangan dan di tag docblock.
 * Deskripsi docblock, komentar prosa, dan string tidak dihitung.
 */
final class MixedMetrics
{
    public static function count(string $path): int
    {
        $count = 0;

        foreach (PhpToken::tokenize(SourceFile::read($path)) as $token) {
            $count += match ($token->id) {
                T_STRING => strtolower($token->text) === 'mixed' ? 1 : 0,
                T_DOC_COMMENT => self::inTags($token->text),
                default => 0,
            };
        }

        return $count;
    }

    /** Konvensi PHPDoc: deskripsi di atas, tag di bawah; tipe hanya hidup di bagian tag. */
    private static function inTags(string $docblock): int
    {
        $firstTag = strpos($docblock, '@');

        if ($firstTag === false) {
            return 0;
        }

        return preg_match_all('/\bmixed\b/', substr($docblock, $firstTag)) ?: 0;
    }
}
