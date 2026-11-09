<?php

// RT1, BR-IAM-21, W168 — setiap request user aktif adalah aktivitas sesi; dorongannya ke layar
// user dibatasi sekali per menit per user. Kehadiran tetap dibaca dari SessionRegistry.

use App\Events\Identity\UserSessionActive;
use App\Http\Middleware\BroadcastSessionActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;

it('request user aktif mendorong session.active, paling sering sekali per menit', function () {
    Event::fake([UserSessionActive::class]);
    $sari = User::factory()->create(['is_active' => true]);

    $this->actingAs($sari)->get(route('dashboard'));
    $this->actingAs($sari)->get(route('dashboard'));
    Event::assertDispatchedTimes(UserSessionActive::class, 1);
    Event::assertDispatched(UserSessionActive::class, fn (UserSessionActive $e) => $e->userId === $sari->id);

    $this->travel(BroadcastSessionActivity::THROTTLE_SECONDS + 1)->seconds();
    $this->actingAs($sari)->get(route('dashboard'));
    Event::assertDispatchedTimes(UserSessionActive::class, 2);
});

it('throttle berlaku per user, bukan global', function () {
    Event::fake([UserSessionActive::class]);
    $sari = User::factory()->create(['is_active' => true]);
    $budi = User::factory()->create(['is_active' => true]);

    $this->actingAs($sari)->get(route('dashboard'));
    $this->actingAs($budi)->get(route('dashboard'));

    Event::assertDispatchedTimes(UserSessionActive::class, 2);
});

it('tamu dan akun nonaktif tidak mendorong session.active', function () {
    Event::fake([UserSessionActive::class]);

    $this->get(route('login'))->assertOk();
    $this->actingAs(User::factory()->create(['is_active' => false]))
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    Event::assertNotDispatched(UserSessionActive::class);
});

it('bentuk siaran session.active', function () {
    $at = CarbonImmutable::parse('2026-11-09 10:00:00');
    $e = new UserSessionActive(7, $at);

    expect($e->broadcastOn()->name)->toBe('private-identity.activity')
        ->and($e->broadcastAs())->toBe('session.active')
        ->and($e->broadcastWith())->toBe(['userId' => 7, 'at' => $at->toIso8601String()])
        ->and($e)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($e)->not->toBeInstanceOf(ShouldBroadcastNow::class);
});
