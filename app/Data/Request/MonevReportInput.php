<?php

namespace App\Data\Request;

use Illuminate\Http\UploadedFile;

/** Isian laporan monev: jawaban per field, berkas, dan cap waktu suntingan (BR-FE-14). */
final class MonevReportInput
{
    /**
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas
     */
    public function __construct(
        public readonly array $answers,
        public readonly array $uploads,
        public readonly ?string $lastUpdatedAt,
    ) {}
}
