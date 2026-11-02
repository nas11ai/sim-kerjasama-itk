<?php

namespace App\Policies;

use App\Models\User;

/**
 * BR-IAM-16, W47 (pemilik 2026-11-02) — Operator ikut menerbitkan dan mencabut undangan.
 * Satu definisi untuk route, `can` halaman, dan guard Action.
 */
class InvitationPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->canAny(['users.verify', 'users.manage']);
    }
}
