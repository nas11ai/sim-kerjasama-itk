<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\ExportFormat;
use App\Queries\Submission\OperatorSubmissionList;
use App\Queries\Submission\SubmissionStatusChoices;
use App\Queries\Submission\SubmissionYear;
use App\Support\Reporting\ExportType;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-07-02 — pilihan formulir "Request Export Baru" (W154). Skema dan status sama
 * dengan daftar Operator; tahun = tahun submit pertama, ditambah tahun berjalan.
 *
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type TypeOption array{value: string, label: string, filterKeys: list<string>}
 * @phpstan-type Result array{types: list<TypeOption>, formats: list<Option>, schemeOptions: list<Option>, statusOptions: list<Option>, yearOptions: list<Option>}
 *
 * @method static Result run()
 */
class ExportFormOptions implements ChangesNoState
{
    use AsAction;

    /** @return Result */
    public function handle(): array
    {
        return [
            'types' => array_map(fn (string $type): array => [
                'value' => $type,
                'label' => ExportType::label($type),
                'filterKeys' => ExportType::definition($type)->filterKeys(),
            ], ExportType::all()),
            'formats' => array_map(
                fn (ExportFormat $format): array => ['value' => $format->value, 'label' => $format->label()],
                ExportFormat::cases(),
            ),
            'schemeOptions' => OperatorSubmissionList::periodAndSchemeOptions()['schemeOptions'],
            'statusOptions' => SubmissionStatusChoices::submitted(),
            'yearOptions' => $this->yearOptions(),
        ];
    }

    /** @return list<Option> */
    private function yearOptions(): array
    {
        return array_map(fn (int $year): array => ['value' => (string) $year, 'label' => (string) $year], SubmissionYear::withCurrent());
    }
}
