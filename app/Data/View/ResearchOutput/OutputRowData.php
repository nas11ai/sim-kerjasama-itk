<?php

namespace App\Data\View\ResearchOutput;

use Spatie\LaravelData\Data;

/**
 * Satu baris luaran (RESEARCHER-06-01, tab Luaran Operator). `headline` dan `meta` disusun
 * backend dari spesifikasi tipenya (BR-RO-09), jadi layar tidak menebak kunci metadata.
 */
final class OutputRowData extends Data
{
    public function __construct(
        public int $id,
        public string $typeName,
        public string $headline,
        public ?string $meta,
        public int $fileCount,
    ) {}
}
