<?php

namespace App\Listeners;

use App\Models\User;
use App\Queries\Notification\UnreadNotificationCount;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * BR-NOT-05, W41 — notifikasi in-app baru membuat hitungan ber-cache basi.
 * Channel lain (mail) tidak menambah baris, jadi tidak perlu dilupakan.
 */
class ForgetUnreadNotificationCount
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || !$event->notifiable instanceof User) {
            return;
        }

        UnreadNotificationCount::forget($event->notifiable);
    }
}
