<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/** Satu baris ADMIN-08-07: aturan akses beserta jumlah tahap yang memakainya di versi mana pun (BR-FE-20). */
final class FormAccessRuleData extends Data
{
    public function __construct(
        public int $id,
        public string $permission,
        public string $permissionLabel,
        public string $organizationLabel,
        public int $phaseCount,
    ) {}
}
