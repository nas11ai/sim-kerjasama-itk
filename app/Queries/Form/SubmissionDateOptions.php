<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\SubmissionDate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — pilihan tenggat untuk satu detail tahap formulir, berlabel
 * "Periode — Label — tanggal" supaya Admin tahu tenggat milik periode mana.
 *
 * @method static list<array{id: int, label: string}> run()
 */
class SubmissionDateOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<array{id: int, label: string}> */
    public function handle(): array
    {
        $options = [];

        $dates = SubmissionDate::query()
            ->with(['submissionPeriod:id,name', 'submissionDateLabel:id,name'])
            ->orderByDesc('datetime')
            ->get();

        foreach ($dates as $date) {
            $options[] = [
                'id' => $date->id,
                'label' => implode(' — ', [
                    (string) $date->submissionPeriod?->name,
                    (string) $date->submissionDateLabel?->name,
                    $date->datetime->format('d/m/Y H:i'),
                ]),
            ];
        }

        return $options;
    }
}
