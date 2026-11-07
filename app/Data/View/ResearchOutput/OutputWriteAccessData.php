<?php

namespace App\Data\View\ResearchOutput;

use Spatie\LaravelData\Data;

/**
 * W146 — boleh-tidaknya menulis luaran satu pengajuan, dihitung `OutputWriteAccess`. Layar hanya
 * membaca ini; tombol yang tersembunyi dan 403 di server tidak pernah berselisih. `overrideUntil`
 * tanpa alasannya (BR-FE-16).
 */
final class OutputWriteAccessData extends Data
{
    public function __construct(
        public bool $canWrite,
        public ?string $blockedReason,
        public ?string $deadline,
        public ?string $overrideUntil,
    ) {}
}
