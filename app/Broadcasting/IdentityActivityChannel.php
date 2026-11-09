<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * RT1 — `identity.activity`: dorongan `session.active` untuk layar user (W168, tanpa kanal presence).
 */
class IdentityActivityChannel
{
    public function join(User $user): bool
    {
        // BR-IAM-21, W47, IAM-SESSION-03 — hanya yang boleh membaca daftar user yang boleh tahu siapa sedang aktif.
        return $user->can('viewAny', User::class);
    }
}
