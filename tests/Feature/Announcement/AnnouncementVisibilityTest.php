<?php

// Visibilitas pengumuman: publik = semua orang; privat = hanya pengguna login;
// yang `expired_at`-nya lewat tidak tampil di mana pun selain layar admin.

use App\Models\Announcement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeAnnouncement(string $type = 'public', ?DateTimeInterface $expiredAt = null, string $title = 'Pengumuman'): Announcement
{
    return Announcement::create([
        'title' => $title,
        'content' => 'Isi pengumuman.',
        'type' => $type,
        'expired_at' => $expiredAt,
        'created_by' => User::factory()->create()->id,
    ]);
}

it('hides private announcements from guests on the detail page', function () {
    $private = makeAnnouncement('private');

    $this->get(route('announcements.detail', $private))->assertNotFound();
});

it('hides expired announcements from guests on the detail page', function () {
    $expired = makeAnnouncement('public', now()->subDay());

    $this->get(route('announcements.detail', $expired))->assertNotFound();
});

it('shows a public announcement to guests on the detail page', function () {
    $public = makeAnnouncement('public');

    $this->get(route('announcements.detail', $public))->assertOk();
});

it('shows a private announcement to a signed-in user on the detail page', function () {
    $private = makeAnnouncement('private');

    $this->actingAs(User::factory()->create())
        ->get(route('announcements.detail', $private))
        ->assertOk();
});

it('hides expired announcements from signed-in users on the detail page', function () {
    $expired = makeAnnouncement('private', now()->subDay());

    $this->actingAs(User::factory()->create())
        ->get(route('announcements.detail', $expired))
        ->assertNotFound();
});

it('leaves expired announcements out of the user list', function () {
    makeAnnouncement('private', null, 'Masih berlaku');
    makeAnnouncement('private', now()->subDay(), 'Kedaluwarsa');

    $this->actingAs(User::factory()->create())
        ->get(route('user.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Announcements/IndexPage')
            ->has('announcements.data', 1)
            ->where('announcements.data.0.title', 'Masih berlaku'));
});

it('shows only unexpired public announcements on the welcome page, at most ten', function () {
    makeAnnouncement('private', null, 'Privat');
    makeAnnouncement('public', now()->subDay(), 'Lewat');
    foreach (range(1, 12) as $i) {
        makeAnnouncement('public', null, "Publik {$i}");
    }

    $this->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('announcements.data', 10)
            ->where('announcements.total', 12)
            ->where('announcements.data', fn ($rows) => collect($rows)
                ->every(fn ($row) => str_starts_with($row['title'], 'Publik'))));
});
