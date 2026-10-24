<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-14 — tipe target luaran: taksonomi aktif ∩ izin skema, supaya layar tidak menawarkan
 * yang pasti ditolak Action. Skema tanpa `allowed_output_types` tidak membatasi apa pun; itu
 * berbeda dari daftar kosong, yang berarti tidak ada tipe yang diizinkan.
 *
 * @method static list<array{code: string, name: string}> run(FormSubmission $submission)
 */
class TargetOutputTypeChoices implements ChangesNoState
{
    use AsAction;

    /** @return list<array{code: string, name: string}> */
    public function handle(FormSubmission $submission): array
    {
        $rules = $submission->scheme?->rules;
        $allowed = is_array($rules) ? ($rules['allowed_output_types'] ?? null) : null;

        return array_values(OutputTypeDefinition::query()
            ->where('is_active', true)
            ->when(is_array($allowed), fn (Builder $q) => $q->whereIn('code', $allowed))
            ->get()
            ->map(fn (OutputTypeDefinition $type): array => ['code' => $type->code, 'name' => $type->name])
            ->all());
    }
}
