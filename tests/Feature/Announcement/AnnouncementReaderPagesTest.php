<?php

// fe-v2-design §4.7 — kontrak layar baca pengumuman: detail, penanda baca (W33), jumlah
// belum dibaca (W32), panel login (W6), dan isi yang tersaring saat dibaca (K6).

use App\Models\Announcement;
use App\Models\Form;
use App\Models\FormType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('researcher');
});

function readerAnnouncement(array $attributes = [], array $roles = []): Announcement
{
    $announcement = Announcement::create(array_merge([
        'title' => 'Pengumuman',
        'content' => '<p>Isi.</p>',
        'type' => 'private',
        'created_by' => User::factory()->create(['name' => 'Operator LPPM'])->id,
    ], $attributes));
    $announcement->syncTargetRoles($roles);

    return $announcement;
}

it('ShowPage membawa isi, lampiran, dan isRead', function () {
    $announcement = readerAnnouncement(['type' => 'public', 'expired_at' => '2026-12-31 23:59:59']);
    $announcement->announcementFiles()->create([
        'file_name' => 'panduan.pdf', 'file_path' => 'announcements/panduan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10,
    ]);
    $page = fn (?bool $isRead) => fn (Assert $page) => $page->component('Announcement/ShowPage')
        ->where('announcement.id', $announcement->id)
        ->where('announcement.contentHtml', '<p>Isi.</p>')
        ->where('announcement.author', 'Operator LPPM')
        ->has('announcement.publishedAt')
        ->has('announcement.expiresAt')
        ->where('announcement.files.0.name', 'panduan.pdf')
        ->where('announcement.isRead', $isRead);

    $this->actingAs($this->researcher)->get(route('announcements.detail', $announcement))->assertInertia($page(false));
    $this->post(route('user.announcements.markRead', $announcement));
    $this->get(route('announcements.detail', $announcement))->assertInertia($page(true));

    auth()->logout();
    $this->get(route('announcements.detail', $announcement))->assertInertia($page(null));
});

it('markRead pada pengumuman tak terlihat dijawab 404', function () {
    $hidden = readerAnnouncement([], ['operator']);
    $expired = readerAnnouncement(['expired_at' => now()->subDay()]);

    $this->actingAs($this->researcher);
    $this->post(route('user.announcements.markRead', $hidden))->assertNotFound();
    $this->post(route('user.announcements.markRead', $expired))->assertNotFound();
    $this->post(route('user.announcements.markRead', 999999))->assertNotFound();

    $this->assertDatabaseCount('announcement_users', 0);
});

it('markRead tidak dihadang gerbang biodata', function () {
    $type = FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);
    Form::factory()->create(['form_type_id' => $type->id, 'is_active' => true]);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Mahasiswa', 'web'));
    $announcement = readerAnnouncement();

    $this->actingAs($user)
        ->from(route('announcements.detail', $announcement))
        ->post(route('user.announcements.markRead', $announcement))
        ->assertRedirect(route('announcements.detail', $announcement));

    $this->assertDatabaseHas('announcement_users', ['announcement_id' => $announcement->id, 'user_id' => $user->id]);
});

it('markRead dua kali tidak menggandakan baris', function () {
    $announcement = readerAnnouncement();

    $this->actingAs($this->researcher);
    $this->post(route('user.announcements.markRead', $announcement))->assertRedirect();
    $this->post(route('user.announcements.markRead', $announcement))->assertRedirect();

    $this->assertDatabaseCount('announcement_users', 1);
});

it('jumlah belum dibaca hanya menghitung yang terlihat', function () {
    $read = readerAnnouncement();
    $read->announcementReader()->attach($this->researcher->id);
    readerAnnouncement();
    readerAnnouncement(['type' => 'public']);
    readerAnnouncement([], ['operator']);
    readerAnnouncement(['expired_at' => now()->subDay()]);

    $this->actingAs($this->researcher)->get(route('user.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page->where('navigation.unreadAnnouncements', 2));
});

it('halaman login membawa paling banyak lima pengumuman publik yang berlaku', function () {
    foreach (range(1, 7) as $i) {
        readerAnnouncement(['type' => 'public', 'title' => "Publik {$i}", 'created_at' => now()->subMinutes(10 - $i)]);
    }
    readerAnnouncement(['title' => 'Privat']);
    readerAnnouncement(['type' => 'public', 'title' => 'Lewat', 'expired_at' => now()->subDay()]);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/LoginPage')
            ->has('announcements', 5)
            ->where('announcements.0.title', 'Publik 7')
            ->where('announcements.0.isRead', null)
            ->where('announcements', fn ($rows) => collect($rows)
                ->every(fn ($row) => str_starts_with($row['title'], 'Publik'))));
});

it('baris lama disaring saat dibaca', function () {
    $announcement = readerAnnouncement(['content' => '<p>a</p><img src=x onerror=alert(1)>']);

    $this->actingAs($this->researcher)->get(route('announcements.detail', $announcement))
        ->assertInertia(fn (Assert $page) => $page->where('announcement.contentHtml', fn (string $html) => str_contains($html, '<p>a</p>')
            && !str_contains($html, 'onerror')
            && !str_contains($html, '<img')));
});

it('kutipan berupa teks polos', function () {
    readerAnnouncement(['content' => '<h2>Judul</h2><p>Isi &amp; lain</p>']);

    $this->actingAs($this->researcher)->get(route('user.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Announcement/IndexPage')
            ->where('announcements.data.0.excerpt', 'Judul Isi & lain')
            ->where('announcements.data.0.attachmentCount', 0)
            ->where('announcements.data.0.isRead', false));
});
