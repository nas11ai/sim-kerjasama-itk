<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\PhaseType;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan "Tipe Tahap" detail tahap (ADMIN-08-09), urut nama; `value` id tipe sebagai string.
 *
 * @method static list<array{value: string, label: string}> run()
 */
class PhaseTypeOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<array{value: string, label: string}> */
    public function handle(): array
    {
        return array_values(PhaseType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (PhaseType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
            ->all());
    }
}
