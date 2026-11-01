<?php

// fe-v2-design §4.7 — pengumuman privat bisa dipersempit per peran; cukup cocok satu.
// Tamu hanya melihat yang publik. Target hanya mencocokkan lima peran DDD (W36).

use App\Models\Announcement;
use App\Models\User;
use App\Queries\Announcement\VisibleAnnouncements;
use App\Support\Announcement\AnnouncementAudience;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function audienceAnnouncement(string $type = 'private', array $roles = [], mixed $expiredAt = null): Announcement
{
    $announcement = Announcement::create([
        'title' => 'Pengumuman',
        'content' => '<p>Isi.</p>',
        'type' => $type,
        'expired_at' => $expiredAt,
        'created_by' => User::factory()->create()->id,
    ]);
    $announcement->syncTargetRoles($roles);

    return $announcement;
}

function userWithRoles(string ...$roles): User
{
    $user = User::factory()->create();
    $user->assignRole($roles);

    return $user;
}

function sees(?User $viewer, Announcement $announcement): bool
{
    return VisibleAnnouncements::run($viewer)->whereKey($announcement->id)->exists();
}

it('privat tanpa target terlihat semua pengguna login', function () {
    $announcement = audienceAnnouncement();

    expect(sees(userWithRoles('researcher'), $announcement))->toBeTrue()
        ->and(sees(User::factory()->create(), $announcement))->toBeTrue();
});

it('privat bertarget hanya terlihat pemegang salah satu peran', function () {
    $announcement = audienceAnnouncement('private', ['researcher', 'operator']);

    expect(sees(userWithRoles('researcher'), $announcement))->toBeTrue()
        ->and(sees(userWithRoles('reviewer_external'), $announcement))->toBeFalse();
});

it('pengguna dua peran cukup cocok satu', function () {
    $announcement = audienceAnnouncement('private', ['operator']);

    expect(sees(userWithRoles('researcher', 'operator'), $announcement))->toBeTrue();
});

it('tamu tidak melihat privat bertarget', function () {
    $announcement = audienceAnnouncement('private', ['researcher']);

    expect(sees(null, $announcement))->toBeFalse();
});

it('Super Admin tanpa peran target tidak melihatnya di jalur pembaca', function () {
    Role::findOrCreate('Super Admin');
    $announcement = audienceAnnouncement('private', ['admin']);

    expect(sees(userWithRoles('Super Admin'), $announcement))->toBeFalse();
});

it('terlihat sampai akhir hari kedaluwarsanya', function () {
    Carbon::setTestNow('2026-11-01 08:00:00');
    $announcement = audienceAnnouncement('public', [], '2026-11-01 23:59:59');
    $viewer = userWithRoles('researcher');

    Carbon::setTestNow('2026-11-01 23:59:00');
    expect(sees($viewer, $announcement))->toBeTrue();

    Carbon::setTestNow('2026-11-02 00:00:01');
    expect(sees($viewer, $announcement))->toBeFalse();
});

it('peran yang bisa dipilih adalah lima peran DDD', function () {
    expect(array_keys(AnnouncementAudience::ROLE_LABELS))
        ->toBe(array_keys(PermissionSeeder::ROLE_PERMISSIONS))
        ->and(AnnouncementAudience::options()[0])->toBe(['value' => 'researcher', 'label' => 'Dosen']);
});

it('describe menamai sasaran pengumuman', function () {
    expect(AnnouncementAudience::describe('public', []))->toBe('Publik')
        ->and(AnnouncementAudience::describe('private', []))->toBe('Semua pengguna login')
        ->and(AnnouncementAudience::describe('private', ['researcher', 'operator']))->toBe('Dosen, Operator LPPM');
});

it('operator memegang announcements.manage', function () {
    expect(Role::findByName('operator')->hasPermissionTo('announcements.manage'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('announcements.manage'))->toBeTrue();
});
