<?php

namespace App\Events\Identity;

use App\Enums\SessionEndReason;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RT1 — memberi tahu tab user bahwa seluruh sesinya sudah diakhiri, supaya ia keluar tanpa
 * menunggu request berikutnya. Diantrekan, bukan `ShouldBroadcastNow` (W169): Reverb yang mati
 * tidak boleh menggagalkan deaktivasi; penegakannya tetap EnsureAccountActive dan baris `sessions`.
 */
class UserSessionsEnded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly SessionEndReason $reason) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("App.Models.User.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'sessions.ended';
    }

    /** @return array{reason: string} */
    public function broadcastWith(): array
    {
        return ['reason' => $this->reason->value];
    }
}
