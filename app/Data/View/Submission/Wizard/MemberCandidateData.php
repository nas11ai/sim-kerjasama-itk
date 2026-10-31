<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** Hasil pencarian calon anggota; `blockedReason` terisi bila BR-SM-08 menolaknya. */
final class MemberCandidateData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $nidn,
        public ?string $blockedReason,
    ) {}
}
