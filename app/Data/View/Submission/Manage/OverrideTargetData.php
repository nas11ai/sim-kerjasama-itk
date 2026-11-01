<?php

namespace App\Data\View\Submission\Manage;

use Spatie\LaravelData\Data;

/**
 * Yang disentuh perpanjangan tenggat (OPERATOR-02-03): detail fase pengajuan dan reviewer
 * aktif yang form evaluasinya sudah lewat tenggat.
 */
final class OverrideTargetData extends Data
{
    /** @param  list<string>  $affectedReviewers */
    public function __construct(
        public ?string $phaseLabel,
        public array $affectedReviewers,
    ) {}
}
