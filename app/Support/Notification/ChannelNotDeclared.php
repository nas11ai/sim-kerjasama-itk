<?php

namespace App\Support\Notification;

use RuntimeException;

/**
 * BR-NOT-06 — tipe event dipublish tanpa deklarasi channel.
 *
 * Ditolak keras, bukan didiamkan dengan default. Daftar channel adalah
 * konfigurasi yang bisa tertinggal saat event baru lahir; kalau ketiadaannya
 * dijawab dengan "kirim in-app saja", kelalaian itu menjadi notifikasi yang
 * diam-diam tidak sampai ke orang yang menunggunya — dan tidak ada yang tahu
 * sampai seseorang mengeluh.
 */
class ChannelNotDeclared extends RuntimeException
{
    public static function for(string $eventType): self
    {
        return new self(
            "Tipe event \"{$eventType}\" belum punya deklarasi channel. "
            .'Tetapkan in-app, email, atau keduanya di Konfigurasi Umum sebelum event ini dipakai.'
        );
    }
}
