<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Data\View\SubmissionStatusData;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\FirstSubmitAt;
use Carbon\CarbonImmutable;

/**
 * W154 — Rekap Submission (DDD: id, judul, status, scheme, lead researcher, tanggal
 * submit, total budget). Tanggal submit = submit pertama (W7); anggaran tanpa baris terhapus.
 */
final class SubmissionRecapExport implements ExportDefinition
{
    public function label(): string
    {
        return 'Rekap Submission';
    }

    public function filterKeys(): array
    {
        return ExportedSubmissions::FILTER_KEYS;
    }

    public function headings(): array
    {
        return ['ID', 'Judul', 'Status', 'Skema', 'Ketua', 'Tanggal Submit', 'Total Anggaran'];
    }

    public function rowCount(User $user, ExportFilters $filters): int
    {
        return ExportedSubmissions::for($user, $filters)->count();
    }

    public function rows(User $user, ExportFilters $filters): array
    {
        $submissions = ExportedSubmissions::for($user, $filters)
            ->with(['form:id,title', 'scheme:id,name', 'submittedBy:id,name', 'budgetLineItems:id,form_submission_id,total'])
            ->addSelect(['form_submissions.*', 'first_submitted_at' => FirstSubmitAt::subquery()])
            ->orderBy('form_submissions.id')
            ->get();

        return array_values($submissions->map(fn (FormSubmission $s): array => [
            $s->id,
            $s->displayTitle(),
            SubmissionStatusData::of($s)->label,
            $s->scheme?->name,
            $s->submittedBy->name,
            self::submittedOn($s),
            array_sum(array_map(fn (BudgetLineItem $item): int => $item->total, $s->budgetLineItems->all())),
        ])->all());
    }

    private static function submittedOn(FormSubmission $submission): ?string
    {
        $at = $submission->getAttribute('first_submitted_at');

        return is_string($at) ? CarbonImmutable::parse($at)->format('Y-m-d') : null;
    }
}
