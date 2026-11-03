<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\SubmissionDate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — pilihan tenggat untuk satu detail tahap formulir, berlabel
 * "Periode — Label — tanggal" supaya Admin tahu tenggat milik periode mana. Hanya tenggat
 * yang boleh dipilih menurut BR-FE-17 (`SubmissionDate::selectableFor`).
 *
 * @method static list<array{id: int, label: string}> run(?FormPhase $phase = null)
 */
class SubmissionDateOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<array{id: int, label: string}> */
    public function handle(?FormPhase $phase = null): array
    {
        $options = [];

        $dates = SubmissionDate::query()
            ->selectableFor($phase)
            ->with(['submissionPeriod:id,name'])
            ->orderByDesc('datetime')
            ->get();

        foreach ($dates as $date) {
            $options[] = [
                'id' => $date->id,
                'label' => implode(' — ', [
                    $date->submissionPeriod->name ?? '',
                    $date->label(),
                    $date->datetime->format('d/m/Y H:i'),
                ]),
            ];
        }

        return $options;
    }
}
