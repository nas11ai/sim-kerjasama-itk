<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\EvaluationSectionData;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Form evaluasi di editor tahap ADMIN-08-09: satu bagian per detail ber-`needs_review`, urut
 * `order`, aktif maupun nonaktif. Semua lewat eager load, tanpa kueri per form atau per field.
 *
 * @method static list<EvaluationSectionData> run(FormPhase $phase)
 */
class PhaseEvaluationForms implements ChangesNoState
{
    use AsAction;

    /** @return list<EvaluationSectionData> */
    public function handle(FormPhase $phase): array
    {
        return array_values($phase->formPhaseDetails()
            ->where('needs_review', true)
            ->with([
                'phaseType:id,name',
                'formAccessControl.form:id,title',
                'reviewEvaluationForms' => fn (Relation $forms) => $forms
                    ->orderBy('order')
                    ->orderBy('id')
                    ->withCount('reviewerFormAssignments')
                    ->with(['reviewFormFields' => fn (Relation $fields) => $fields
                        ->orderBy('order')
                        ->orderBy('id')
                        ->withCount('reviewFormFieldResponses')
                        ->with(['reviewFormFieldOptions' => fn (Relation $options) => $options->orderBy('order')->orderBy('id')]),
                    ]),
            ])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (FormPhaseDetail $detail): EvaluationSectionData => EvaluationSectionData::fromModel($detail))
            ->all());
    }
}
