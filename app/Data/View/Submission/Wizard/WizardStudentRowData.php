<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** BR-SM-20 — anggota mahasiswa: data, bukan akun. */
final class WizardStudentRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $studentId,
        public ?string $studyProgram,
    ) {}
}
