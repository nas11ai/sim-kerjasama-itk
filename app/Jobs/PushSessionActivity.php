<?php

namespace App\Jobs;

use App\Events\Identity\UserSessionActive;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * RT1, W169 — dorongan `session.active` lewat antrean, supaya request user tidak menunggu Reverb.
 * E2E-15 — saat Reverb mati dorongannya dibuang: tidak dicoba ulang, tidak masuk `failed_jobs`.
 */
class PushSessionActivity implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $userId, public readonly CarbonImmutable $at) {}

    public function handle(): void
    {
        try {
            UserSessionActive::dispatch($this->userId, $this->at);
        } catch (BroadcastException|GuzzleException) {
            // Kehadiran tetap dibaca dari SessionRegistry, dan ping basi digantikan ping semenit
            // kemudian (BR-IAM-21); mencatatnya menambah satu baris failed_jobs per menit per user.
            return;
        }
    }
}
