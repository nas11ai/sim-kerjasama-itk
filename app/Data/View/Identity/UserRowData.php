<?php

namespace App\Data\View\Identity;

use App\Models\User;
use App\Support\Identity\RoleCatalog;
use Spatie\LaravelData\Data;

/** Satu baris OPERATOR-08-01; relasi `roles`, `userProfile`, `organization` dimuat UserDirectory. */
final class UserRowData extends Data
{
    /** @param  list<array{value: string, label: string}>  $roles */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $nidn,
        public ?string $organization,
        public array $roles,
        public AccountStatusData $status,
        public string $showUrl,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            nidn: $user->userProfile?->nidn,
            organization: $user->organization?->name,
            roles: RoleCatalog::optionsOf($user->roles),
            status: AccountStatusData::of($user),
            showUrl: route('admin.users.show', $user),
        );
    }
}
