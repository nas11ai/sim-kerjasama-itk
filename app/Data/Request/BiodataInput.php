<?php

namespace App\Data\Request;

use Illuminate\Http\UploadedFile;

/** Isian biodata: jawaban per field, berkas unggahan per field, dan apakah dikirim final. */
final class BiodataInput
{
    /**
     * @param  array<int, ?string>  $responses  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas
     */
    public function __construct(
        public readonly int $formId,
        public readonly bool $isSubmitted,
        public readonly array $responses,
        public readonly array $uploads = [],
    ) {}
}
