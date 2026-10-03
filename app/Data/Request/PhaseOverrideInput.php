<?php

namespace App\Data\Request;

/** BR-FE-09 — masukan perpanjangan akses fase: alasan wajib, tenggat boleh kosong. */
final class PhaseOverrideInput
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $expiresAt = null,
    ) {}
}
