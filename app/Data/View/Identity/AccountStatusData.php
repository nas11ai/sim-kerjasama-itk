<?php

namespace App\Data\View\Identity;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Support\Identity\SessionRegistry;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * W49 — dua sumbu keadaan akun (DDD "Status Akun dan Status Organisasi"): keaktifan login dan
 * status verifikasi NIDN. Verifikasi null = terverifikasi, akun undangan, atau tanpa profil.
 * Kehadiran (BR-IAM-21) ikut di sini karena tampil di badge yang sama.
 */
final class AccountStatusData extends Data
{
    /** @param  array{value: string, label: string}|null  $verification */
    public function __construct(
        public bool $active,
        public ?array $verification,
        public bool $online,
        public ?string $lastActiveAt,
        // E2E-16 — jam server saat status dibaca; klien mengukur selisih jam perangkatnya dari
        // sini, jadi badge "Sedang aktif" tidak ikut meleset bersama jam perangkat.
        public string $checkedAt,
    ) {}

    /** BR-IAM-21 — `$lastActiveAt` dari SessionRegistry; null = tak ada sesi tercatat. */
    public static function of(User $user, ?CarbonImmutable $lastActiveAt): self
    {
        $status = $user->userProfile?->verification_status;

        return new self(
            active: $user->is_active,
            verification: $status === null || $status === VerificationStatus::Active
                ? null
                : ['value' => $status->value, 'label' => $status->label()],
            online: SessionRegistry::isOnline($lastActiveAt),
            lastActiveAt: $lastActiveAt?->toIso8601String(),
            checkedAt: CarbonImmutable::now()->toIso8601String(),
        );
    }
}
