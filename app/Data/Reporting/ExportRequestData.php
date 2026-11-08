<?php

namespace App\Data\Reporting;

use App\Enums\ExportFormat;
use Spatie\LaravelData\Data;

/**
 * W154 — satu permintaan ekspor tabular: jenis, format, dan filter. Kunci filter di luar
 * `filterKeys()` jenisnya dibuang `RequestExport`, satu tempat untuk semua jalur masuk.
 */
final class ExportRequestData extends Data
{
    public function __construct(
        public string $type,
        public ExportFormat $format = ExportFormat::Xlsx,
        public ExportFilters $filters = new ExportFilters,
    ) {}
}
