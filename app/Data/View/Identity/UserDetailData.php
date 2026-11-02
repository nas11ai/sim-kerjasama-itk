<?php

namespace App\Data\View\Identity;

use App\Models\User;
use Spatie\LaravelData\Data;

/** "Data Personal" OPERATOR-08-03. */
final class UserDetailData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $nidn,
        public ?string $phone,
        public ?string $organization,
        public ?string $expertise,
        public ?string $registeredAt,
        public AccountStatusData $status,
    ) {}

    public static function fromModel(User $user): self
    {
        $profile = $user->userProfile;

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            nidn: $profile?->nidn,
            phone: $profile?->phone,
            organization: $user->organization?->name,
            expertise: $profile?->expertise,
            registeredAt: $user->created_at?->toIso8601String(),
            status: AccountStatusData::of($user),
        );
    }
}
