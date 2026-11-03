<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\SubmissionDate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — pilihan tenggat detail tahap, berlabel "Periode — Label — tanggal", hanya yang
 * boleh dipilih menurut BR-FE-17 (`selectableFor`). Jenis, siklus, dan periodenya ikut dikirim
 * supaya layar menawarkan tiap tenggat di kolom jenisnya, satu periode dan siklus (BR-FE-26).
 *
 * @phpstan-type Option array{id: int, type: string, cycle: int|null, periodId: int, label: string}
 *
 * @method static list<Option> run(?FormPhase $phase = null)
 */
class SubmissionDateOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<Option> */
    public function handle(?FormPhase $phase = null): array
    {
        $dates = SubmissionDate::query()
            ->selectableFor($phase)
            ->with(['submissionPeriod:id,name'])
            ->orderByDesc('datetime')
            ->get();

        return array_values($dates->map(self::option(...))->all());
    }

    /**
     * Satu tenggat berbentuk pilihan; juga untuk tenggat tersimpan di luar pilihan (W102).
     *
     * @return Option
     */
    public static function option(SubmissionDate $date): array
    {
        return [
            'id' => $date->id,
            'type' => $date->type->value,
            'cycle' => $date->cycle,
            'periodId' => $date->submission_period_id,
            'label' => implode(' — ', [
                $date->submissionPeriod->name ?? '',
                $date->label(),
                $date->datetime->format('d/m/Y H:i'),
            ]),
        ];
    }
}
