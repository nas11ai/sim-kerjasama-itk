<?php

namespace Database\Seeders;

use App\Models\NotificationChannelSetting;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;
use Illuminate\Database\Seeder;

/**
 * BR-NOT-06 — default channel per tipe event.
 *
 * Default-nya "masuk akal", bukan "aman": mengirim email untuk segalanya akan
 * membuat email berhenti dibaca untuk apa pun, dan yang pertama hilang justru
 * yang mendesak. Peristiwa yang menuntut tindakan segera dapat keduanya;
 * peristiwa yang sekadar memberi kabar cukup in-app.
 *
 * `updateOrCreate` dengan hanya mengisi yang belum ada: seeder yang dijalankan
 * ulang tidak boleh mengembalikan pilihan yang sudah disunting Admin.
 */
class NotificationChannelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NotificationEventCatalog::defaults() as $eventType => $channels) {
            NotificationChannelSetting::query()->firstOrCreate(
                ['event_type' => $eventType],
                ['channels' => $channels],
            );
        }

        NotificationChannels::forget();
    }
}
