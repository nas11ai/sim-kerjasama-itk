<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Queries\ResearchOutput\OutputRows;
use Illuminate\Database\Eloquent\Builder;

/**
 * W154 — Daftar Luaran: satu baris per luaran pengajuan yang diekspor. Luaran ikut batas
 * induknya (BR-RPT-10); judulnya dibaca dari spesifikasi tipe, sama dengan layar (BR-RO-09).
 */
final class ResearchOutputListExport implements ExportDefinition
{
    public function label(): string
    {
        return 'Daftar Luaran';
    }

    public function filterKeys(): array
    {
        return ExportedSubmissions::FILTER_KEYS;
    }

    public function headings(): array
    {
        return ['ID Pengajuan', 'Judul Pengajuan', 'Tipe Luaran', 'Judul Luaran', 'Dibuat'];
    }

    public function rowCount(User $user, ExportFilters $filters): int
    {
        return $this->query($user, $filters)->count();
    }

    public function rows(User $user, ExportFilters $filters): array
    {
        $outputs = $this->query($user, $filters)
            ->with(['formSubmission:id,form_id,title', 'formSubmission.form:id,title'])
            ->orderBy('form_submission_id')
            ->orderBy('id')
            ->get();
        $types = OutputTypeDefinition::query()->whereIn('code', $outputs->pluck('output_type')->unique())->get()->keyBy('code');

        return array_values($outputs->map(fn (ResearchOutput $output): array => [
            $output->form_submission_id,
            $output->formSubmission->displayTitle(),
            OutputRows::typeName($output, $types->get($output->output_type)),
            OutputRows::headline($output, $types->get($output->output_type)),
            $output->created_at?->format('Y-m-d'),
        ])->all());
    }

    /** @return Builder<ResearchOutput> */
    private function query(User $user, ExportFilters $filters): Builder
    {
        return ResearchOutput::query()->whereIn('form_submission_id', ExportedSubmissions::idsFor($user, $filters));
    }
}
