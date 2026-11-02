<?php

namespace App\Data\View\Identity;

use App\Models\UserProfile;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/** Satu kartu OPERATOR-08-02; relasi `user` dan `organization` dimuat VerificationQueue. */
final class VerificationCardData extends Data
{
    /** @param  list<array{label: string, url: string}>  $files */
    public function __construct(
        public int $profileId,
        public string $name,
        public string $email,
        public ?string $nidn,
        public ?string $organization,
        public ?string $registeredAt,
        public ?string $rejectedAt,
        public ?string $rejectionReason,
        public array $files,
    ) {}

    /** @param  list<array{label: string, url: string}>  $files */
    public static function fromModel(UserProfile $profile, ?CarbonImmutable $rejectedAt, array $files): self
    {
        return new self(
            profileId: $profile->id,
            name: $profile->user->name ?? '—',
            email: $profile->user->email ?? '—',
            nidn: $profile->nidn,
            organization: $profile->organization?->name,
            registeredAt: $profile->created_at?->toIso8601String(),
            rejectedAt: $rejectedAt?->toIso8601String(),
            rejectionReason: $profile->rejection_reason,
            files: $files,
        );
    }
}
