<?php

namespace App\Data\View;

use App\States\Submission\SubmissionStatus;
use Spatie\LaravelData\Data;

/**
 * Status pengajuan sebagaimana ditampilkan di layar. Label dan varian
 * dimiliki backend, jadi Vue tidak menyimpan peta status sendiri.
 */
final class SubmissionStatusData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $variant,
    ) {}

    public static function fromState(SubmissionStatus $state): self
    {
        return new self(
            key: $state->key(),
            label: $state->label(),
            variant: $state->variant(),
        );
    }
}
