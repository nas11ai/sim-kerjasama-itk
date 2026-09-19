<?php

namespace App\Support\Quality;

/**
 * Mencari blok enam baris yang muncul di lebih dari satu berkas. Lebih pendek
 * dari enam menangkap kebetulan: urutan `import` dan penutup kurung muncul
 * identik di mana-mana.
 */
final class DuplicationScanner
{
    private const WINDOW = 6;

    private const MIN_LINE_LENGTH = 12;

    /** Komentar dibuang tanpa memutus blok, sama seperti baris pendek. */
    private const COMMENT_PREFIXES = ['//', '*'];

    /** Deklarasi impor memutus blok: urutannya milik formatter, bukan logika yang disalin. */
    private const IMPORT_PREFIXES = ['namespace ', 'use ', 'import '];

    /** @param list<string> $paths */
    public static function countSharedBlocks(array $paths): int
    {
        return count(array_filter(
            self::index($paths),
            static fn (array $owners): bool => count($owners) > 1,
        ));
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, int> path => jumlah bloknya yang juga ada di berkas lain
     */
    public static function filesSharing(array $paths): array
    {
        $shared = [];

        foreach (self::index($paths) as $owners) {
            if (count($owners) < 2) {
                continue;
            }

            foreach ($owners as $path) {
                $shared[$path] = ($shared[$path] ?? 0) + 1;
            }
        }

        return $shared;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, list<string>> hash blok => berkas yang memuatnya
     */
    private static function index(array $paths): array
    {
        $index = [];

        foreach ($paths as $path) {
            foreach (self::blocks($path) as $hash) {
                $index[$hash] ??= [];

                if (!in_array($path, $index[$hash], true)) {
                    $index[$hash][] = $path;
                }
            }
        }

        return $index;
    }

    /** @return list<string> */
    private static function blocks(string $path): array
    {
        $blocks = [];

        foreach (self::segments(SourceFile::read($path)) as $lines) {
            for ($i = 0; $i + self::WINDOW <= count($lines); $i++) {
                $blocks[] = md5(implode(' ', array_slice($lines, $i, self::WINDOW)));
            }
        }

        return $blocks;
    }

    /**
     * Baris bermakna, dipotong di setiap deklarasi impor. Blok tidak pernah melintasi
     * impor, jadi mengabaikan impor hanya mengurangi temuan, tidak menambahnya.
     *
     * @return list<list<string>>
     */
    private static function segments(string $source): array
    {
        $lines = array_map(
            static fn (string $line): string => trim(preg_replace('/\s+/', ' ', $line) ?? ''),
            SourceFile::lines($source),
        );
        $imports = self::importMask($lines);
        $segments = [];
        $current = [];

        foreach ($lines as $i => $line) {
            if ($imports[$i]) {
                $segments[] = $current;
                $current = [];

                continue;
            }

            if (strlen($line) > self::MIN_LINE_LENGTH && !self::startsWithAny($line, self::COMMENT_PREFIXES)) {
                $current[] = $line;
            }
        }

        return [...$segments, $current];
    }

    /**
     * @param  list<string>  $lines
     * @return list<bool> baris mana yang bagian dari deklarasi impor
     */
    private static function importMask(array $lines): array
    {
        $mask = [];
        $open = false;

        foreach ($lines as $line) {
            $starts = self::startsWithAny($line, self::IMPORT_PREFIXES);
            $mask[] = $open || $starts;
            // Impor lintas baris dibuka `import {` dan ditutup `} from '...'`.
            $open = $open ? !str_starts_with($line, '}') : $starts && str_ends_with($line, '{');
        }

        return $mask;
    }

    /** @param list<string> $prefixes */
    private static function startsWithAny(string $line, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($line, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
