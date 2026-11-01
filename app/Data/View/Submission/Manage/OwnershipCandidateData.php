<?php

namespace App\Data\View\Submission\Manage;

use Spatie\LaravelData\Data;

/** Calon ketua baru di OPERATOR-02-04: co-investigator aktif (BR-SM-09). */
final class OwnershipCandidateData extends Data
{
    public function __construct(
        public int $userId,
        public string $name,
        public ?string $organization,
    ) {}
}
