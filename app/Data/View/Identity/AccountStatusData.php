<?php

namespace App\Data\View\Identity;

use App\Enums\VerificationStatus;
use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * W49 — dua sumbu keadaan akun (DDD "Status Akun dan Status Organisasi"): keaktifan login dan
 * status verifikasi NIDN. Verifikasi null = terverifikasi, akun undangan, atau tanpa profil.
 */
final class AccountStatusData extends Data
{
    /** @param  array{value: string, label: string}|null  $verification */
    public function __construct(
        public bool $active,
        public ?array $verification,
    ) {}

    public static function of(User $user): self
    {
        $status = $user->userProfile?->verification_status;

        return new self(
            active: $user->is_active,
            verification: $status === null || $status === VerificationStatus::Active
                ? null
                : ['value' => $status->value, 'label' => $status->label()],
        );
    }
}
