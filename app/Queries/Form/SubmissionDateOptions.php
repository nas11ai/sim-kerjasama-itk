<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\SubmissionDate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — pilihan tenggat detail tahap, berlabel "Periode — Label — tanggal", hanya yang
 * boleh dipilih menurut BR-FE-17 (`selectableFor`). Jenis dan siklusnya ikut dikirim supaya
 * layar menawarkan tiap tenggat hanya di kolom jenisnya (BR-FE-26).
 *
 * @method static list<array{id: int, type: string, cycle: int|null, label: string}> run(?FormPhase $phase = null)
 */
class SubmissionDateOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<array{id: int, type: string, cycle: int|null, label: string}> */
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
                'type' => $date->type->value,
                'cycle' => $date->cycle,
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
