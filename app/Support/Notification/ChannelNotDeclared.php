<?php

namespace App\Support\Notification;

use RuntimeException;

/**
 * BR-NOT-06 — tipe event dipublish tanpa deklarasi channel. Ditolak keras,
 * bukan dijawab "kirim in-app saja": daftar channel bisa tertinggal saat event
 * baru lahir, dan kelalaian itu jadi notifikasi yang diam-diam tidak sampai.
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
