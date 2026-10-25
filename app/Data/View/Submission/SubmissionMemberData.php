<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** Ketua atau anggota aktif pengajuan beserta label perannya. */
final class SubmissionMemberData extends Data
{
    public function __construct(
        public string $name,
        public string $roleLabel,
    ) {}
}
