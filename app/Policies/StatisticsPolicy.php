<?php

namespace App\Policies;

use App\Models\User;

class StatisticsPolicy
{
    // W148 — pemetaan reporting.view (§4.4 K1), sama dengan navigasi, jadi menu dan route sepakat.
    public function view(User $user): bool
    {
        return $user->can('reporting.export') || $user->can('reporting.view-audit-log');
    }
}
