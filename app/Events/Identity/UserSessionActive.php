<?php

namespace App\Events\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RT1, W168 — aktivitas sesi seorang user, untuk badge "Sedang aktif" di layar user. Bukan
 * kanal presence: hanya pemegang users.verify/users.manage yang mendengarnya (IAM-SESSION-03).
 * Diantrekan (W169), jadi request user tidak pernah menunggu Reverb.
 */
class UserSessionActive implements ShouldBroadcast
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
