<?php

namespace App\Support\Database;

/**
 * E2E-10 — pola LIKE dari input pengguna. `\` di-escape lebih dulu, lalu `%` dan `_`, supaya
 * ketiganya dicocokkan apa adanya (`\` adalah karakter escape bawaan LIKE di PostgreSQL).
 */
final class LikePattern
{
    public static function contains(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
