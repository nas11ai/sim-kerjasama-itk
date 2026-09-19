<?php

namespace App\Notifications\Concerns;

use App\Support\Notification\NotificationChannels;
use LogicException;

/**
 * BR-NOT-06 — channel diambil dari konfigurasi, bukan ditulis di kelasnya.
 * Kunci deklarasinya `EVENT_TYPE`, bukan nama kelas yang berubah saat refactor;
 * kelas yang lupa menyatakannya ditangkap `NotificationChannelTest`.
 */
trait UsesConfiguredChannels
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $eventType = static::EVENT_TYPE;

        // Trait tidak bisa menjanjikan konstanta ini ada maupun bertipe string.
        if (!is_string($eventType)) {
            throw new LogicException(static::class.' harus menyatakan EVENT_TYPE bertipe string.');
        }

        return NotificationChannels::for($eventType);
    }
}
