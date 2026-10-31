<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** Langkah 4 wizard; batas null berarti skema (atau ketiadaannya) tidak membatasi. */
final class WizardMembersData extends Data
{
    /**
     * @param  list<WizardMemberRowData>  $members
     * @param  list<WizardStudentRowData>  $students
     * @param  list<WizardPartnerRowData>  $partners
     * @param  list<array{value: string, label: string}>  $roleOptions
     */
    public function __construct(
        public array $members,
        public array $students,
        public array $partners,
        public ?int $memberLimit,
        public ?int $studentLimit,
        public array $roleOptions,
    ) {}
}
