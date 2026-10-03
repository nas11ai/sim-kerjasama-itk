<?php

namespace App\Support\Audit;

/**
 * BR-RPT-09 — nilai lama dan baru satu perubahan. Keduanya selalu bepergian
 * bersama ke layar audit, jadi mereka satu parameter, bukan dua.
 */
final class AuditChange
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __construct(
        public readonly ?array $old = null,
        public readonly ?array $new = null,
    ) {}
}
