<?php

namespace App\Support\Quality;

use RuntimeException;

/** Membaca berkas sumber untuk para pengukur; berkas yang tak terbaca selalu dilempar. */
final class SourceFile
{
    public static function read(string $path): string
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Berkas tidak terbaca: {$path}");
        }

        return $contents;
    }

    /** Sama dengan `wc -l` untuk berkas yang diakhiri baris baru. */
    public static function lineCount(string $source): int
    {
        if ($source === '') {
            return 0;
        }

        $newlines = substr_count($source, "\n");

        return str_ends_with($source, "\n") ? $newlines : $newlines + 1;
    }

    /** @return list<string> */
    public static function lines(string $source): array
    {
        $lines = preg_split('/\R/', $source);

        if ($lines === false) {
            throw new RuntimeException('Gagal memecah baris sumber.');
        }

        return $lines;
    }
}
