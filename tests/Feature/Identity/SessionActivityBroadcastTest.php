<?php

// RT1, BR-IAM-21, W168 — setiap request user aktif adalah aktivitas sesi; dorongannya ke layar
// user dibatasi sekali per menit per user. Kehadiran tetap dibaca dari SessionRegistry.

use App\Events\Identity\UserSessionActive;
use App\Http\Middleware\BroadcastSessionActivity;
use App\Jobs\PushSessionActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('request user aktif mengantrekan session.active, paling sering sekali per menit', function () {
    Queue::fake([PushSessionActivity::class]);
    $sari = User::factory()->create(['is_active' => true]);

    $this->actingAs($sari)->get(route('dashboard'));
    $this->actingAs($sari)->get(route('dashboard'));
    Queue::assertPushedTimes(PushSessionActivity::class, 1);
    Queue::assertPushed(PushSessionActivity::class, fn (PushSessionActivity $job) => $job->userId === $sari->id);

    $this->travel(BroadcastSessionActivity::THROTTLE_SECONDS + 1)->seconds();
    $this->actingAs($sari)->get(route('dashboard'));
    Queue::assertPushedTimes(PushSessionActivity::class, 2);
});

it('throttle berlaku per user, bukan global', function () {
    Queue::fake([PushSessionActivity::class]);
    $sari = User::factory()->create(['is_active' => true]);
    $budi = User::factory()->create(['is_active' => true]);

    $this->actingAs($sari)->get(route('dashboard'));
    $this->actingAs($budi)->get(route('dashboard'));

    Queue::assertPushedTimes(PushSessionActivity::class, 2);
});

it('tamu dan akun nonaktif tidak mendorong session.active', function () {
    Queue::fake([PushSessionActivity::class]);

    $this->get(route('login'))->assertOk();
    $this->actingAs(User::factory()->create(['is_active' => false]))
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    Queue::assertNotPushed(PushSessionActivity::class);
});

it('bentuk siaran session.active', function () {
    $at = CarbonImmutable::parse('2026-11-09 10:00:00');
    $e = new UserSessionActive(7, $at);

    expect($e->broadcastOn()->name)->toBe('private-identity.activity')
        ->and($e->broadcastAs())->toBe('session.active')
        ->and($e->broadcastWith())->toBe(['userId' => 7, 'at' => $at->toIso8601String()])
        ->and($e)->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and(new PushSessionActivity(7, $at))->toBeInstanceOf(ShouldQueue::class);
});

it('job memancarkan session.active user itu', function () {
    Event::fake([UserSessionActive::class]);
    $at = CarbonImmutable::parse('2026-11-09 10:00:00');

    PushSessionActivity::dispatchSync(7, $at);

    Event::assertDispatched(UserSessionActive::class, fn (UserSessionActive $e) => $e->userId === 7 && $e->at->equalTo($at));
});

// E2E-15 — Reverb mati: dorongannya dibuang, tidak dicoba ulang, tidak masuk failed_jobs.
it('Reverb mati tidak membuat job gagal dan tidak dicoba ulang', function () {
    Broadcast::extend('mati', fn () => new class extends Broadcaster
    {
        public function auth($request): mixed
        {
            return null;
        }

        public function validAuthenticationResponse($request, $result): mixed
        {
            return null;
        }

        /** @param  array<int, mixed>  $channels */
        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw new BroadcastException('Pusher error: cURL error 7: Failed to connect.');
        }
    });
    config(['broadcasting.connections.mati' => ['driver' => 'mati'], 'broadcasting.default' => 'mati']);

    // Dipanggil langsung, bukan lewat expect()->not->toThrow(): tanpa penangkapan di job,
    // BroadcastException naik ke worker dan job tercatat di failed_jobs — test ini gagal.
    PushSessionActivity::dispatchSync(7, CarbonImmutable::now());

    expect((new PushSessionActivity(7, CarbonImmutable::now()))->tries)->toBe(1);
});
