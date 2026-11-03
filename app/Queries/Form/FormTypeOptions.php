<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormType;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W80 — Biodata tidak ditawarkan untuk Form baru maupun sebagai tipe pengganti; Form
 * yang sudah bertipe Biodata tetap melihat tipenya sendiri. FormDefinitionRequest menegakkannya.
 *
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static list<Option> run(?Form $form)
 */
class FormTypeOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<Option> */
    public function handle(?Form $form): array
    {
        return array_values(FormType::query()
            ->when(!self::keepsBiodata($form), fn (Builder $q) => $q->whereKeyNot(FormType::BIODATA_ID))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FormType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
            ->all());
    }

    public static function keepsBiodata(?Form $form): bool
    {
        return $form?->form_type_id === FormType::BIODATA_ID;
    }
}
