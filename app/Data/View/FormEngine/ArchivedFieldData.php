<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/** BR-FE-12 / W83 — field yang dihapus diarsipkan; tampil read-only di bawah daftar. */
final class ArchivedFieldData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public string $typeName,
    ) {}
}
