<?php

namespace App\Data\Request;

use Illuminate\Http\UploadedFile;

/** Isian satu langkah wizard pengajuan: form dan periodenya, jawaban, berkas, dan cap waktu suntingan. */
final class SubmissionAnswersInput
{
    /**
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas
     * @param  ?string  $lastUpdatedAt  BR-FE-14 — `updated_at` pengajuan yang dilihat penyunting
     */
    public function __construct(
        public readonly int $formId,
        public readonly int $submissionPeriodId,
        public readonly array $answers,
        public readonly array $uploads,
        public readonly ?string $lastUpdatedAt,
    ) {}
}
