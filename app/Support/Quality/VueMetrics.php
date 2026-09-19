<?php

namespace App\Support\Quality;

/**
 * Mengukur berkas `.vue`, hanya LOC. Kompleksitas `<script setup>` butuh parser
 * TypeScript, sementara diagnosis menunjukkan bloat Vue hidup di ukuran berkas.
 */
final class VueMetrics
{
    public static function loc(string $path): int
    {
        return SourceFile::lineCount(SourceFile::read($path));
    }
}
