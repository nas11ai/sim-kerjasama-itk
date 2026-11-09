<?php

namespace App\Support\Search;

/**
 * Pola `whereLike` (ILIKE di PostgreSQL) yang mencocokkan istilah sebagai teks biasa:
 * `%` dan `_` ketikan pengguna di-escape supaya tidak menjadi wildcard yang memuat semua baris.
 */
final class LikePattern
{
    public static function contains(string $term): string
    {
        return '%'.self::escape($term).'%';
    }

    /** Untuk mengurutkan nama yang diawali istilah lebih dulu (K2). */
    public static function startsWith(string $term): string
    {
        return self::escape($term).'%';
    }

    private static function escape(string $term): string
    {
        return addcslashes($term, '\\%_');
    }
}
