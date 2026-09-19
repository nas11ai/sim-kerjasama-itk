<?php

namespace App\Support\Quality;

/**
 * Menghitung baris prosa komentar terpanjang dalam satu berkas. Anotasi
 * dikecualikan: PHPStan level 10 menuntut docblock yang kaya, dan membatasinya
 * akan membuat dua aturan repo ini saling berkelahi.
 */
final class CommentMetrics
{
    private const MIN_PROSE_LENGTH = 4;

    public static function longestProseRun(string $path): int
    {
        $longest = 0;
        $current = 0;

        foreach (SourceFile::lines(SourceFile::read($path)) as $line) {
            $trimmed = trim($line);

            if (!self::isCommentLine($trimmed)) {
                $longest = max($longest, $current);
                $current = 0;

                continue;
            }

            if (self::isProse($trimmed)) {
                $current++;
            }
        }

        return max($longest, $current);
    }

    private static function isCommentLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '/*');
    }

    /** Baris prosa: ada isinya, bukan anotasi, bukan pembuka atau penutup blok. */
    private static function isProse(string $trimmed): bool
    {
        $body = trim(preg_replace('#^(//+|/\*+|\*+/?)#', '', $trimmed) ?? '');

        if (strlen($body) < self::MIN_PROSE_LENGTH) {
            return false;
        }

        return !str_starts_with($body, '@');
    }
}
