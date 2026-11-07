<?php

namespace App\Data\Request;

use Illuminate\Http\UploadedFile;

/** BR-RO-01..09, W141 — masukan luaran: berkas berupa unggahan, bukan path dari klien. */
final class ResearchOutputInput
{
    /**
     * @param  array<string, mixed>  $metadata  dibersihkan Guard menurut spesifikasi tipe (W140)
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public readonly string $outputType,
        public readonly array $metadata = [],
        public readonly array $files = [],
    ) {}
}
