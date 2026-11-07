<?php

namespace App\Data\View\Scheme;

use App\Models\Scheme;
use App\Models\TechnologyReadinessLevel;
use App\Support\Scheme\SchemeRuleCatalog;
use Spatie\LaravelData\Data;

/**
 * ADMIN-08-04 — skema yang disunting. Aturan katalog dibaca seperti pembacanya membacanya:
 * nilai yang bukan bilangan bulat positif atau bukan daftar kode tampil kosong (W107).
 *
 * @phpstan-type Rules array{min_reviewer_count: ?int, max_reviewer_workload: ?int, max_student_members: ?int, allowed_output_types: list<string>}
 */
final class SchemeEditData extends Data
{
    /**
     * @param  list<int>  $allowedTrlIds
     * @param  Rules  $rules
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public bool $isActive,
        public ?int $schemeTypeId,
        public ?int $submissionTypeId,
        public int $maxBudget,
        public int $maxMembers,
        public int $durationMonths,
        public array $allowedTrlIds,
        public array $rules,
    ) {}

    public static function fromModel(Scheme $scheme): self
    {
        return new self(
            id: $scheme->id,
            name: $scheme->name,
            code: $scheme->code,
            isActive: $scheme->is_active,
            schemeTypeId: $scheme->scheme_type_id,
            submissionTypeId: $scheme->submission_type_id,
            maxBudget: $scheme->max_budget,
            maxMembers: $scheme->max_members,
            durationMonths: $scheme->duration_months,
            allowedTrlIds: array_values($scheme->allowedTrls->map(fn (TechnologyReadinessLevel $trl): int => $trl->id)
                ->sort()->values()->all()),
            rules: [
                'min_reviewer_count' => $scheme->positiveIntRule('min_reviewer_count'),
                'max_reviewer_workload' => $scheme->positiveIntRule('max_reviewer_workload'),
                'max_student_members' => $scheme->positiveIntRule('max_student_members'),
                'allowed_output_types' => $scheme->codeListRule(SchemeRuleCatalog::OUTPUT_TYPES_KEY),
            ],
        );
    }
}
