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
        $lines = self::significantLines(SourceFile::read($path));
        $blocks = [];

        for ($i = 0; $i + self::WINDOW <= count($lines); $i++) {
            $blocks[] = md5(implode(' ', array_slice($lines, $i, self::WINDOW)));
        }

        return $blocks;
    }

    /** @return list<string> */
    private static function significantLines(string $source): array
    {
        $lines = [];

        foreach (SourceFile::lines($source) as $line) {
            $normalised = trim(preg_replace('/\s+/', ' ', $line) ?? '');

            if (strlen($normalised) <= self::MIN_LINE_LENGTH) {
                continue;
            }

            if (str_starts_with($normalised, '//') || str_starts_with($normalised, '*')) {
                continue;
            }

            $lines[] = $normalised;
        }

        return $lines;
    }
}
