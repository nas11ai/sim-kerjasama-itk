<?php

namespace App\Enums;

use App\Models\InvitationToken;

/**
 * BR-IAM-06 — keadaan undangan beserta sebabnya (W51). Peleburan pesan anti-enumerasi (W3)
 * hanya berlaku di layar pendaftar; Operator perlu tahu kenapa sebuah tautan mati.
 */
enum InvitationStatus: string
{
    case Valid = 'valid';

    case Revoked = 'revoked';

    case OrganizationInactive = 'organization_inactive';

    case Exhausted = 'exhausted';

    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Revoked => 'Dicabut',
            self::OrganizationInactive => 'Organisasi Nonaktif',
            self::Exhausted => 'Kuota Habis',
            self::Expired => 'Kedaluwarsa',
        };
    }

    // Urutan prioritas mengikuti daftar case; `valid` hanya bila isValid() setuju.
    public static function of(InvitationToken $invitation): self
    {
        return match (true) {
            $invitation->isValid() => self::Valid,
            $invitation->revoked_at !== null => self::Revoked,
            !($invitation->organization->is_active ?? false) => self::OrganizationInactive,
            $invitation->max_uses !== null && $invitation->used_count >= $invitation->max_uses => self::Exhausted,
            default => self::Expired,
        };
    }

    /** @return array{value: string, label: string} */
    public function option(): array
    {
        return ['value' => $this->value, 'label' => $this->label()];
    }
}
