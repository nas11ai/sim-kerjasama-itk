<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\ExportFilters;
use App\Models\ExportJob;
use App\Models\Scheme;
use App\Queries\Submission\SubmissionStatusChoices;
use App\Support\Reporting\ExportType;

/**
 * W154 — kolom "Parameter" riwayat ekspor (frame 07-02 "2026 - Semua Skema"): filter
 * tersimpan dibaca kembali dengan label yang sama dengan pilihan di formulir.
 */
final class ExportParameters implements ChangesNoState
{
    /** @var array<int, string> */
    private array $schemes;

    /** @var array<string, string> */
    private array $statuses;

    /** @param  list<int>  $schemeIds  skema yang disebut baris-baris riwayat */
    public function __construct(array $schemeIds)
    {
        $this->schemes = Scheme::query()->whereIn('id', $schemeIds)->get(['id', 'name'])
            ->mapWithKeys(fn (Scheme $scheme): array => [$scheme->id => $scheme->name])
            ->all();
        $this->statuses = array_column(SubmissionStatusChoices::submitted(), 'label', 'value');
    }

    public function describe(ExportJob $job, ExportFilters $filters): string
    {
        $keys = ExportType::definition($job->type)->filterKeys();
        $parts = [self::years($filters)];

        if (in_array(ExportFilters::SCHEME, $keys, true)) {
            $parts[] = $filters->scheme === null ? 'Semua skema' : ($this->schemes[$filters->scheme] ?? "Skema #{$filters->scheme}");
        }

        if ($filters->status !== null) {
            $parts[] = $this->statuses[$filters->status] ?? $filters->status;
        }

        return implode(' · ', $parts);
    }

    private static function years(ExportFilters $filters): string
    {
        [$from, $to] = [$filters->yearFrom, $filters->yearTo];

        return match (true) {
            $from === null && $to === null => 'Semua tahun',
            $from === $to => (string) $from,
            $to === null => "Sejak {$from}",
            $from === null => "Hingga {$to}",
            default => "{$from} – {$to}",
        };
    }
}
