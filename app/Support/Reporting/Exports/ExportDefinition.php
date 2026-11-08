<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Models\User;

/**
 * W154 — satu jenis ekspor tabular DDD: kolomnya, barisnya, dan filter yang ia terima.
 * Baris selalu berangkat dari `SubmissionVisibility`/`AuditScope` peminta (BR-RPT-10).
 */
interface ExportDefinition
{
    public function label(): string;

    /** @return list<string> kunci `ExportFilters::YEAR|SCHEME|STATUS` yang berlaku */
    public function filterKeys(): array;

    /** @return list<string> */
    public function headings(): array;

    /** BR-RPT-02 — jumlah baris berkas, untuk ambang background job. */
    public function rowCount(User $user, ExportFilters $filters): int;

    /** @return list<list<string|int|null>> */
    public function rows(User $user, ExportFilters $filters): array;
}
