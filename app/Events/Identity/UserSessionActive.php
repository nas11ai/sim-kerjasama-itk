<?php

namespace App\Events\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RT1, W168 — aktivitas sesi untuk badge "Sedang aktif"; bukan kanal presence, hanya pemegang
 * users.verify/users.manage yang mendengarnya (IAM-SESSION-03). Disiarkan langsung di dalam job
 * PushSessionActivity yang diantrekan (W169), supaya kegagalannya bisa dibuang di sana (E2E-15).
 */
class UserSessionActive implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly CarbonImmutable $at) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('identity.activity');
    }

    public function broadcastAs(): string
    {
        return 'session.active';
    }

    /** @return array{userId: int, at: string} */
    public function broadcastWith(): array
    {
        return ['userId' => $this->userId, 'at' => $this->at->toIso8601String()];
    }
}
