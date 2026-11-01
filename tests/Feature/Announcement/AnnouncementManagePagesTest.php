<?php

// fe-v2-design §4.7 — kontrak layar kelola pengumuman: Operator mengelola tanpa
// users.manage (K5), target peran, kedaluwarsa per hari (W34), isi rich text tersaring (K6/K6a).

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(PermissionSeeder::class);
    $this->operator = User::factory()->create(['name' => 'Operator LPPM']);
    $this->operator->assignRole('operator');
});

function managedAnnouncement(array $attributes = [], array $roles = []): Announcement
{
    $announcement = Announcement::create(array_merge([
        'title' => 'Seminar Hibah',
        'content' => '<p>Isi.</p>',
        'type' => 'private',
        'created_by' => User::factory()->create()->id,
    ], $attributes));
    $announcement->syncTargetRoles($roles);

    return $announcement;
}

function managePayload(array $overrides = []): array
{
    return array_merge(['title' => 'Judul', 'content' => '<p>Isi pengumuman.</p>', 'type' => 'public'], $overrides);
}

function storeAnnouncement(User $actor, array $overrides = []): Announcement
{
    test()->actingAs($actor)->post(route('admin.announcements.store'), managePayload($overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.announcements.index'));

    return Announcement::query()->latest('id')->firstOrFail();
}

it('Operator tanpa users.manage membuka Announcement/ManageIndexPage', function () {
    expect($this->operator->can('users.manage'))->toBeFalse();
    managedAnnouncement();

    $this->actingAs($this->operator)->get(route('admin.announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Announcement/ManageIndexPage')
            ->has('announcements.data', 1)
            ->has('announcements.data.0', fn (Assert $row) => $row
                ->hasAll(['id', 'title', 'audienceLabel', 'expiresAt', 'expired', 'createdAt', 'author']))
            ->where('filters.search', ''));
});

it('peneliti ditolak di index, create, store, edit, update, destroy', function () {
    $researcher = User::factory()->create();
    $researcher->assignRole('researcher');
    $announcement = managedAnnouncement();
    $this->actingAs($researcher);

    $this->get(route('admin.announcements.index'))->assertForbidden();
    $this->get(route('admin.announcements.create'))->assertForbidden();
    $this->post(route('admin.announcements.store'), managePayload())->assertForbidden();
    $this->get(route('admin.announcements.edit', $announcement))->assertForbidden();
    $this->put(route('admin.announcements.update', $announcement), managePayload())->assertForbidden();
    $this->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
});

it('pengumuman kedaluwarsa tetap tampil dengan tanda', function () {
    managedAnnouncement(['expired_at' => now()->subDay()]);

    $this->actingAs($this->operator)->get(route('admin.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page->where('announcements.data.0.expired', true));
});

it('audienceLabel mengikuti jenis dan target', function () {
    managedAnnouncement(['title' => 'A', 'created_at' => now()->subMinutes(2)], ['researcher', 'operator']);
    managedAnnouncement(['title' => 'B', 'type' => 'public', 'created_at' => now()->subMinute()]);

    $this->actingAs($this->operator)->get(route('admin.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('announcements.data.0.audienceLabel', 'Publik')
            ->where('announcements.data.1.audienceLabel', 'Dosen, Operator LPPM'));
});

it('pencarian judul tidak peka huruf', function () {
    managedAnnouncement(['title' => 'Seminar Hibah']);
    managedAnnouncement(['title' => 'Libur Nasional']);

    $this->actingAs($this->operator)->get(route('admin.announcements.index', ['search' => 'SEMINAR']))
        ->assertInertia(fn (Assert $page) => $page->has('announcements.data', 1)
            ->where('announcements.data.0.title', 'Seminar Hibah')
            ->where('filters.search', 'SEMINAR'));
});

it('FormPage sunting membawa target peran dan lampiran', function () {
    $announcement = managedAnnouncement(['expired_at' => '2026-12-31 23:59:59'], ['researcher']);
    $announcement->announcementFiles()->create([
        'file_name' => 'panduan.pdf', 'file_path' => 'announcements/panduan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 2048,
    ]);

    $this->actingAs($this->operator)->get(route('admin.announcements.edit', $announcement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Announcement/FormPage')
            ->where('announcement.id', $announcement->id)
            ->where('announcement.targetRoles', ['researcher'])
            ->where('announcement.expiredOn', '2026-12-31')
            ->where('announcement.files.0.name', 'panduan.pdf')
            ->where('announcement.files.0.size', 2048)
            ->has('announcement.files.0.url')
            ->has('roleOptions', 5));
});

it('FormPage buat membawa lima pilihan peran', function () {
    $this->actingAs($this->operator)->get(route('admin.announcements.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Announcement/FormPage')
            ->where('announcement', null)
            ->where('roleOptions.0', ['value' => 'researcher', 'label' => 'Dosen'])
            ->has('roleOptions', 5));
});

it('target peran pada pengumuman publik ditolak', function () {
    $this->actingAs($this->operator)
        ->post(route('admin.announcements.store'), managePayload(['target_roles' => ['researcher']]))
        ->assertSessionHasErrors(['target_roles' => 'Pengumuman publik tidak bisa dibatasi per peran.']);
});

it('peran di luar lima peran DDD ditolak', function () {
    $this->actingAs($this->operator)
        ->post(route('admin.announcements.store'), managePayload(['type' => 'private', 'target_roles' => ['Admin']]))
        ->assertSessionHasErrors('target_roles.0');
});

it('revisi ke publik melepas target peran', function () {
    $announcement = managedAnnouncement([], ['researcher']);

    $this->actingAs($this->operator)
        ->put(route('admin.announcements.update', $announcement), managePayload())
        ->assertSessionHasNoErrors();

    expect($announcement->targetRoles()->count())->toBe(0);
});

it('audit penerbitan dan revisi memuat target peran', function () {
    $announcement = storeAnnouncement($this->operator, ['type' => 'private', 'target_roles' => ['researcher']]);
    $this->put(route('admin.announcements.update', $announcement), managePayload(['type' => 'private', 'target_roles' => ['operator']]));

    $published = Activity::query()->where('description', 'Pengumuman diterbitkan')->sole();
    $revised = Activity::query()->where('description', 'Pengumuman direvisi')->sole();
    expect($published->properties['new']['target_roles'])->toBe(['researcher'])
        ->and($revised->properties['old']['target_roles'])->toBe(['researcher'])
        ->and($revised->properties['new']['target_roles'])->toBe(['operator']);
});

it('tanggal kedaluwarsa disimpan sampai akhir hari', function () {
    $announcement = storeAnnouncement($this->operator, ['expired_at' => '2026-12-31']);

    expect($announcement->expired_at?->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59');
});

it('route admin.announcements.show tidak terdaftar', function () {
    expect(Route::has('admin.announcements.show'))->toBeFalse();
});

it('markup berbahaya dibuang saat disimpan', function () {
    $content = '<p>Halo<script>x</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">t</a><span style="color:red">s</span></p>';

    $stored = storeAnnouncement($this->operator, ['content' => $content])->content;

    expect($stored)->toContain('Halo')
        ->not->toContain('script')
        ->not->toContain('<img')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('style');
});

it('format yang diizinkan tetap utuh', function () {
    $content = '<h2>Judul</h2><ul><li><strong>Satu</strong></li></ul><p><a href="https://itk.ac.id">ITK</a></p>';

    $stored = storeAnnouncement($this->operator, ['content' => $content])->content;

    expect($stored)->toContain('<h2>Judul</h2>')
        ->toContain('<ul><li><strong>Satu</strong></li></ul>')
        ->toContain('href="https://itk.ac.id"')
        ->toContain('target="_blank"')
        ->toMatch('/rel="[^"]*noopener[^"]*"/');
});

it('isi tanpa teks ditolak', function () {
    $this->actingAs($this->operator)
        ->post(route('admin.announcements.store'), managePayload(['content' => '<p></p>']))
        ->assertSessionHasErrors(['content' => 'Isi pengumuman wajib diisi.']);
});

it('gambar sisipan milik sendiri lolos saringan', function () {
    $img = '<img src="/storage/announcements/inline/'.str_repeat('a', 40).'.png" alt="Poster">';

    $stored = storeAnnouncement($this->operator, ['content' => '<p>Lihat</p>'.$img])->content;

    expect($stored)->toContain('src="/storage/announcements/inline/'.str_repeat('a', 40).'.png"')
        ->toContain('alt="Poster"');
});

it('gambar luar, data URI, dan path traversal dibuang', function () {
    $content = '<p>Teks</p><img src="https://evil.example/pixel.png" alt="a">'
        .'<img src="data:image/png;base64,iVBORw0KGgo=" alt="b">'
        .'<img src="/storage/announcements/inline/../../../.env" alt="c">'
        .'<img src="//evil.example/storage/announcements/inline/'.str_repeat('b', 40).'.png" alt="d">';

    $stored = storeAnnouncement($this->operator, ['content' => $content])->content;

    expect($stored)->toContain('Teks')->not->toContain('<img');
});

it('revisi yang membuang gambar menghapus berkasnya', function () {
    $path = 'announcements/inline/'.str_repeat('c', 40).'.png';
    Storage::disk('public')->put($path, 'png');
    $announcement = managedAnnouncement(['content' => '<p>x</p><img src="/storage/'.$path.'" alt="">']);

    $this->actingAs($this->operator)
        ->put(route('admin.announcements.update', $announcement), managePayload(['content' => '<p>tanpa gambar</p>']))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($path);
});

it('penarikan menghapus gambar sisipan yang tak dirujuk pengumuman lain', function () {
    $own = 'announcements/inline/'.str_repeat('d', 40).'.png';
    $shared = 'announcements/inline/'.str_repeat('e', 40).'.jpg';
    Storage::disk('public')->put($own, 'png');
    Storage::disk('public')->put($shared, 'jpg');
    $img = fn (string $path) => '<img src="/storage/'.$path.'" alt="">';
    $announcement = managedAnnouncement(['content' => '<p>x</p>'.$img($own).$img($shared)]);
    managedAnnouncement(['title' => 'Lain', 'content' => '<p>y</p>'.$img($shared)]);

    $this->actingAs($this->operator)->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();

    Storage::disk('public')->assertMissing($own);
    Storage::disk('public')->assertExists($shared);
});
