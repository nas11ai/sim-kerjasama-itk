<?php

// tests/Feature/Migrations/DeclareMissingNotificationChannelsTest.php
//
// BR-NOT-06 — event tanpa deklarasi kanal ditolak saat dikirim, dan Deliver
// melempar ulang penolakan itu. Database yang sudah berjalan sebelum sebuah
// event ditambahkan ke katalog tidak pernah menjalankan seeder lagi, jadi
// deklarasinya harus datang lewat migration — tanpa menimpa suntingan Admin.

use App\Models\NotificationChannelSetting;
use App\Models\User;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;

function runDeclareMissingNotificationChannels(): void
{
    $migration = require database_path('migrations/2026_10_04_100000_declare_missing_notification_channels.php');
    $migration->up();
}

it('menyisipkan deklarasi RevisionResolved yang belum ada dengan default katalog', function () {
    NotificationChannelSetting::query()->where('event_type', 'RevisionResolved')->delete();

    runDeclareMissingNotificationChannels();

    $setting = NotificationChannelSetting::query()->where('event_type', 'RevisionResolved')->sole();

    expect($setting->channels)->toBe(NotificationEventCatalog::defaults()['RevisionResolved'])
        ->and(NotificationChannelSetting::query()->count())->toBe(count(NotificationEventCatalog::defaults()));
});

it('tidak menimpa deklarasi yang sudah disunting Admin', function () {
    $admin = User::factory()->create();
    NotificationChannelSetting::query()->where('event_type', 'ExportCompleted')->update([
        'channels' => json_encode([NotificationChannels::EMAIL]),
        'updated_by' => $admin->id,
    ]);
    NotificationChannelSetting::query()->where('event_type', 'RevisionResolved')->delete();

    runDeclareMissingNotificationChannels();

    $edited = NotificationChannelSetting::query()->where('event_type', 'ExportCompleted')->sole();

    expect($edited->channels)->toBe([NotificationChannels::EMAIL])
        ->and($edited->updated_by)->toBe($admin->id);
});
