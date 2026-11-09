<?php

// BR-IAM-21 — sesi login dibaca (terakhir aktif, sedang aktif = aktivitas ≤ 5 menit) dan
// diakhiri tanpa menonaktifkan akun. Driver test `array`, jadi baris `sessions` disisipkan sendiri.

use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function seedSession(User $user, string $id, int $minutesAgo): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->subMinutes($minutesAgo)->timestamp,
    ]);
}

it('[IAM-SESSION-01] mengakhiri seluruh sesi tanpa menonaktifkan akun', function () {
    $admin = sessionViewer('users.manage');
    $sari = User::factory()->create(['is_active' => true, 'email' => 'sari@itk.ac.id']);
    $oldToken = $sari->remember_token;
    seedSession($sari, 'sari-laptop', 1);
    seedSession($sari, 'sari-hp', 30);
    seedSession($admin, 'admin-laptop', 1);

    $this->actingAs($admin)->post(route('admin.users.sessions.end', $sari))
        ->assertRedirect()
        ->assertSessionHas('success', 'Seluruh sesi user diakhiri.');

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['admin-laptop'])
        ->and($sari->fresh()->is_active)->toBeTrue()
        ->and($sari->fresh()->remember_token)->not->toBe($oldToken);
    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('description', 'Sesi user diakhiri')->sole();
    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe($sari->id)
        ->and($entry->properties['new'])->toBe(['sessions_ended' => 2]);

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'sari@itk.ac.id', 'password' => 'password']);
    $this->assertAuthenticatedAs($sari->fresh());
});

it('[IAM-SESSION-02] sedang aktif hanya dalam jendela 5 menit', function () {
    $this->freezeTime();
    $sari = User::factory()->create(['name' => 'Budi Lama']);
    $rina = User::factory()->create(['name' => 'Rina Baru']);
    seedSession($sari, 'budi', 6);
    seedSession($rina, 'rina-lama', 50);
    seedSession($rina, 'rina', 4);
    $viewer = sessionViewer('users.verify');

    $this->actingAs($viewer)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.1.name', 'Budi Lama')
            ->where('users.data.1.status.online', false)
            ->where('users.data.1.status.lastActiveAt', fn ($at) => is_string($at))
            ->where('users.data.2.name', 'Rina Baru')
            ->where('users.data.2.status.online', true));

    $this->actingAs($viewer)->get(route('admin.users.index', ['presence' => 'online']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Rina Baru')
            ->has('presenceOptions'));

    $this->actingAs($viewer)->get(route('admin.users.show', $sari))
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.status.online', false)
            ->where('user.status.lastActiveAt', now()->subMinutes(6)->startOfSecond()->toIso8601String())
            ->where('can.endSessions', false));
});

it('akun tanpa sesi tidak punya waktu terakhir aktif', function () {
    $target = User::factory()->create();

    $this->actingAs(sessionViewer('users.manage'))->get(route('admin.users.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.status.online', false)
            ->where('user.status.lastActiveAt', null)
            ->where('can.endSessions', true));
});

it('[IAM-SESSION-03] peneliti tidak melihat kehadiran dan Operator tidak mengakhiri sesi', function () {
    $target = User::factory()->create();
    seedSession($target, 'target', 1);

    $researcher = sessionViewer('submissions.create');
    $this->actingAs($researcher)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($researcher)->get(route('admin.users.show', $target))->assertForbidden();
    $this->actingAs($researcher)->post(route('admin.users.sessions.end', $target))->assertForbidden();
    $this->actingAs(sessionViewer('users.verify'))->post(route('admin.users.sessions.end', $target))->assertForbidden();

    expect(DB::table('sessions')->where('id', 'target')->exists())->toBeTrue();
});

it('pemegang users.manage tidak mengakhiri sesinya sendiri lewat layar ini', function () {
    $admin = sessionViewer('users.manage');

    $this->actingAs($admin)->post(route('admin.users.sessions.end', $admin))->assertForbidden();
});
