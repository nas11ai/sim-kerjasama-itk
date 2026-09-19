<?php

namespace App\Support\Quality;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Daftar berkas sumber yang diukur pagar kualitas, relatif terhadap akar repo. */
final class SourceTree
{
    /** @return list<string> seluruh berkas yang diukur pagar: PHP di app/ dan Vue di resources/js/ */
    public static function measured(string $root): array
    {
        return [...self::paths($root, 'app', 'php'), ...self::paths($root, 'resources/js', 'vue')];
    }

    /** @return list<string> terurut, supaya pesan gagal dan daftar legacy stabil */
    public static function paths(string $root, string $dir, string $extension): array
    {
        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            // Iterator tidak bertipe; instanceof menyempitkannya tanpa anotasi.
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === $extension) {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($paths);

        return $paths;
    }
}
