<?php

// tests/Feature/AnnouncementMarkReadTest.php
//
// Tabel pivot untuk status baca pengumuman bernama `announcement_users`
// (lihat database/migrations/2025_08_28_044150_create_announcement_users_table.php),
// tapi Announcement::announcementReader(), User::announcementReads(), dan
// AnnouncementUser::$table semuanya memakai bentuk tunggal
// `announcement_user` — tabel yang tidak pernah ada. Akibatnya route
// user.announcements.markRead selalu melempar
// SQLSTATE[42P01]: relation "announcement_user" does not exist.
// Test ini menembak route itu end-to-end dan memastikan barisnya
// mendarat di `announcement_users`.

use App\Models\Announcement;
use App\Models\AnnouncementUser;
use App\Models\User;

it('marks an announcement as read and stores the row in announcement_users', function () {
    $user = User::factory()->create();
    $announcer = User::factory()->create();

    $announcement = Announcement::create([
        'title' => 'Pengumuman Uji',
        'content' => 'Konten pengumuman uji.',
        'type' => 'public',
        'created_by' => $announcer->id,
    ]);

    $this->actingAs($user)
        ->post(route('user.announcements.markRead', $announcement))
        ->assertOk()
        ->assertJson([
            'message' => 'Pengumuman berhasil ditandai sebagai dibaca.',
        ]);

    expect(
        AnnouncementUser::query()->getModel()->getTable()
    )->toBe('announcement_users');

    $this->assertDatabaseHas('announcement_users', [
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
    ]);
});

it('exposes the read relation from both User and Announcement via announcement_users', function () {
    $user = User::factory()->create();
    $announcer = User::factory()->create();

    $announcement = Announcement::create([
        'title' => 'Pengumuman Uji Relasi',
        'content' => 'Konten pengumuman uji relasi.',
        'type' => 'public',
        'created_by' => $announcer->id,
    ]);

    $announcement->announcementReader()->attach($user->id);

    expect($user->announcementReads()->first()?->id)->toBe($announcement->id)
        ->and($announcement->announcementReader()->first()?->id)->toBe($user->id);
});

it('no longer changes state through GET on the markRead URL', function () {
    $user = User::factory()->create();
    $announcement = Announcement::create([
        'title' => 'Pengumuman GET',
        'content' => 'Konten.',
        'type' => 'private',
        'created_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.announcements.markRead', $announcement))
        ->assertStatus(405);

    $this->assertDatabaseMissing('announcement_users', ['announcement_id' => $announcement->id]);
});
