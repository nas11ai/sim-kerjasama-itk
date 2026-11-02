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
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
