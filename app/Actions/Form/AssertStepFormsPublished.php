<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Models\Form;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-33 / BR-FE-34 (G3-W6) — versi tahap hanya memakai Form yang sudah pernah terbit; Form
 * yang field-nya masih draf belum punya isian bagi peneliti.
 *
 * @method static void run(list<FormPhaseDetailInput> $details)
 */
class AssertStepFormsPublished implements ChangesNoState
{
    use AsAction;

    /**
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    public function handle(array $details): void
    {
        $unpublished = Form::query()
            ->whereHas('formAccessControls', fn ($access) => $access->whereIn('id', array_map(
                fn (FormPhaseDetailInput $d): int => $d->formAccessControlId,
                $details,
            )))
            ->whereNull('published_at')
            ->orderBy('id')
            ->first();

        if ($unpublished === null) {
            return;
        }

        throw ValidationException::withMessages(['phase_details' => "Formulir \"{$unpublished->title}\" belum pernah diterbitkan."]);
    }
}
