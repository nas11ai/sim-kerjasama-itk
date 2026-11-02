<?php

namespace App\Data\View\Identity;

use App\Enums\InvitationPreset;
use App\Enums\InvitationStatus;
use App\Models\InvitationToken;
use Spatie\LaravelData\Data;

/**
 * Satu baris ADMIN-08-01; relasi `organization` dimuat InvitationList. Token-nya sendiri
 * tidak ikut: daftar memperlihatkan keadaan undangan, bukan kredensialnya (W51).
 */
final class InvitationRowData extends Data
{
    /**
     * @param  array{value: string, label: string}|null  $preset
     * @param  array{value: string, label: string}  $status
     */
    public function __construct(
        public int $id,
        public ?string $organization,
        public ?int $organizationId,
        public ?array $preset,
        public int $usedCount,
        public ?int $maxUses,
        public ?int $remainingUses,
        public ?string $expiresAt,
        public array $status,
        public bool $revocable,
    ) {}

    public static function fromModel(InvitationToken $invitation): self
    {
        $preset = InvitationPreset::fromPermissions(array_values($invitation->permissions ?? []));

        return new self(
            id: $invitation->id,
            organization: $invitation->organization?->name,
            organizationId: $invitation->organization_id,
            preset: $preset === null ? null : ['value' => $preset->value, 'label' => $preset->label()],
            usedCount: $invitation->used_count,
            maxUses: $invitation->max_uses,
            remainingUses: $invitation->max_uses === null ? null : max(0, $invitation->max_uses - $invitation->used_count),
            expiresAt: $invitation->expires_at?->toIso8601String(),
            status: InvitationStatus::of($invitation)->option(),
            revocable: $invitation->revoked_at === null,
        );
    }
}
