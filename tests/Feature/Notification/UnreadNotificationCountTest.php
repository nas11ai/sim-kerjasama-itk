<?php

// tests/Feature/Notification/UnreadNotificationCountTest.php
//
// BR-NOT-05, W41 — hitungan belum dibaca di-cache per user. Cache yang tidak
// dilupakan saat notifikasi baru masuk membuat badge berbohong: pekerjaan baru
// tidak terlihat sampai cache kebetulan kedaluwarsa.

use App\Models\Form;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use App\Queries\Notification\UnreadNotificationCount;
use App\Support\Notification\Deliver;

it('cache dilupakan saat notifikasi database terkirim', function () {
    $budi = User::factory()->create();

    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    expect(UnreadNotificationCount::run($budi))->toBe(1);

    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    expect(UnreadNotificationCount::run($budi))->toBe(2);
});

it('forget membuat hitungan dibaca ulang', function () {
    $budi = User::factory()->create();
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));

    expect(UnreadNotificationCount::run($budi))->toBe(1);

    // Ditandai dibaca tanpa lewat controller: cache masih memegang angka lama.
    $budi->unreadNotifications()->update(['read_at' => now()]);
    expect(UnreadNotificationCount::run($budi))->toBe(1);

    UnreadNotificationCount::forget($budi);
    expect(UnreadNotificationCount::run($budi))->toBe(0);
});

it('cache satu user tidak bocor ke user lain', function () {
    $budi = User::factory()->create();
    $dewi = User::factory()->create();
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));

    expect(UnreadNotificationCount::run($budi))->toBe(1)
        ->and(UnreadNotificationCount::run($dewi))->toBe(0);
});
