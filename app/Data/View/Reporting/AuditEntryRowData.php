<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * Satu baris Audit Log (OPERATOR-07-03, W155). `ipAddress` null bagi pembaca tanpa cakupan
 * penuh (UX pattern 07: kolom IP hanya Operator/Admin); nilai bersarang `old`/`new` sudah teks.
 */
final class AuditEntryRowData extends Data
{
    /**
     * @param  array<string, string|int|float|bool|null>|null  $old
     * @param  array<string, string|int|float|bool|null>|null  $new
     */
    public function __construct(
        public int $id,
        public string $createdAt,
        public string $subject,
        public string $action,
        public string $actor,
        public ?string $ipAddress,
        public ?array $old,
        public ?array $new,
    ) {}
}
