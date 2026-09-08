<?php

namespace App\Notifications\Concerns;

use App\Support\Notification\NotificationChannels;

/**
 * BR-NOT-06 — channel diambil dari konfigurasi, bukan ditulis di kelasnya.
 *
 * Setiap notifikasi menyatakan `EVENT_TYPE`-nya; channel-nya urusan tabel.
 * Kelas yang lupa menyatakannya ditangkap `NotificationChannelTest`, bukan
 * dibiarkan memakai nama kelas sebagai kunci — nama kelas berubah saat
 * di-refactor, dan deklarasinya akan ikut hilang tanpa ada yang tahu.
 */
trait UsesConfiguredChannels
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return NotificationChannels::for(static::EVENT_TYPE);
    }
}
