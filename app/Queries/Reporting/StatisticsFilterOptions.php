<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Queries\Submission\OperatorSubmissionList;
use App\Queries\Submission\SubmissionYear;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan filter Dashboard Statistik. Tahun = tahun submit pertama pengajuan induk terkirim,
 * ditambah tahun berjalan (nilai default filter); periode dan skema sama dengan daftar Operator.
 *
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{yearOptions: list<Option>, periodOptions: list<Option>, schemeOptions: list<Option>} run()
 */
class StatisticsFilterOptions implements ChangesNoState
{
    use AsAction;

    /** @return array{yearOptions: list<Option>, periodOptions: list<Option>, schemeOptions: list<Option>} */
    public function handle(): array
    {
        return [
            'yearOptions' => $this->yearOptions(),
            ...OperatorSubmissionList::periodAndSchemeOptions(),
        ];
    }

    /** @return list<Option> */
    private function yearOptions(): array
    {
        $years = array_unique([now()->year, ...SubmissionYear::years()]);
        rsort($years);

        return [
            ['value' => StatisticsFilters::ALL_YEARS, 'label' => 'Semua tahun'],
            ...array_map(fn (int $year): array => ['value' => (string) $year, 'label' => (string) $year], $years),
        ];
    }
}
