<?php

namespace App\Data\View;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\RejectedAt;
use Spatie\LaravelData\Data;

/**
 * BR-IAM-04, BR-IAM-13 — status verifikasi NIDN sebagaimana dibaca pendaftar di
 * SHARED-01-05. Alasan penolakan wajib sampai ke sini: tanpa itu pendaftar tidak
 * tahu apa yang dikoreksi, dan mendaftar ulang mustahil karena NIDN-nya terpakai.
 */
final class AccountVerificationData extends Data
{
    public function __construct(
        public string $status,
        public string $statusLabel,
        public ?string $nidn,
        public string $email,
        public ?string $rejectionReason,
        public ?string $rejectedAt,
    ) {}

    public static function fromProfile(User $user, UserProfile $profile): self
    {
        return new self(
            status: $profile->verification_status->value,
            statusLabel: $profile->verification_status->label(),
            nidn: $profile->nidn,
            email: $user->email,
            rejectionReason: $profile->rejection_reason,
            // W5/W53 — "Ditolak pada" dari jejak audit penolakan terbaru.
            rejectedAt: $profile->verification_status === VerificationStatus::Rejected
                ? RejectedAt::of($profile)?->toIso8601String()
                : null,
        );
    }
}
