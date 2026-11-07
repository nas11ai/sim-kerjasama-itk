<?php

namespace App\Data\View\Scheme;

use App\Models\Scheme;
use Spatie\LaravelData\Data;

/**
 * Satu baris ADMIN-08-03. `active_submissions_count` dimuat SchemeDirectory lewat
 * `withCount`, dengan predikat yang sama seperti DeactivateScheme (BR-SCH-01).
 */
final class SchemeRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public ?string $submissionType,
        public ?string $schemeType,
        public int $maxBudget,
        public int $maxMembers,
        public int $durationMonths,
        public int $activeSubmissionCount,
        public bool $isActive,
    ) {}

    public static function fromModel(Scheme $scheme): self
    {
        return new self(
            id: $scheme->id,
            name: $scheme->name,
            code: $scheme->code,
            submissionType: $scheme->submissionType?->name,
            schemeType: $scheme->schemeType?->name,
            maxBudget: $scheme->max_budget,
            maxMembers: $scheme->max_members,
            durationMonths: $scheme->duration_months,
            activeSubmissionCount: $scheme->active_submissions_count,
            isActive: $scheme->is_active,
        );
    }
}
