<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** Satu baris tabel anggota langkah 4; ketua ber-`id` 0 karena ia bukan baris ResearchMember. */
final class WizardMemberRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
        public string $roleLabel,
        public ?string $unit,
        public ?string $nidn,
        public bool $removable,
    ) {}
}
