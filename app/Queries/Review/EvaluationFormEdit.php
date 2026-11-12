<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\EvaluationDraftInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Data\View\FormEngine\EvaluationFieldEditData;
use App\Data\View\FormEngine\EvaluationFormEditPageData;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Models\ReviewFormField;
use App\Models\StepEvaluationForm;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi editor pustaka Form Evaluasi: field draf bila ada, kalau tidak field versi terbit terakhir
 * (BR-FE-31); riwayat versi terbaru dulu; langkah yang memasangnya; dampak Terbitkan.
 *
 * @method static EvaluationFormEditPageData run(ReviewEvaluationForm $form)
 */
class EvaluationFormEdit implements ChangesNoState
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form): EvaluationFormEditPageData
    {
        $form->load([
            'draft',
            'latestVersion.reviewFormFields.reviewFormFieldOptions' => fn (Relation $options) => $options->orderBy('order')->orderBy('id'),
        ]);

        return new EvaluationFormEditPageData(
            id: $form->id,
            title: $form->title,
            description: $form->description,
            fields: $this->fields($form),
            hasDraft: $form->draft !== null,
            latestVersionLabel: $form->latestVersion?->label(),
            versions: $this->versions($form),
            usedBy: $this->usedBy($form),
            impact: EvaluationPublishImpact::run($form),
        );
    }

    /** @return list<EvaluationFieldEditData> */
    private function fields(ReviewEvaluationForm $form): array
    {
        if ($form->draft !== null) {
            return array_map(
                fn (ReviewFormFieldInput $field): EvaluationFieldEditData => EvaluationFieldEditData::fromInput($field),
                EvaluationDraftInput::fromPayload($form->draft->payload)->fields,
            );
        }

        $version = $form->latestVersion;

        if ($version === null) {
            return [];
        }

        return array_values($version->reviewFormFields
            ->map(fn (ReviewFormField $field): EvaluationFieldEditData => EvaluationFieldEditData::fromModel($field))
            ->all());
    }

    /** @return list<array{label: string, publishedAt: string, publishedBy: string|null}> */
    private function versions(ReviewEvaluationForm $form): array
    {
        return array_values($form->versions()
            ->reorder('number', 'desc')
            ->with('publisher:id,name')
            ->get()
            ->map(fn (ReviewEvaluationFormVersion $version): array => [
                'label' => $version->label(),
                'publishedAt' => $version->published_at->toIso8601String(),
                'publishedBy' => $version->publisher?->name,
            ])
            ->all());
    }

    /** @return list<array{phaseId: int, phaseTitle: string, stepLabel: string}> */
    private function usedBy(ReviewEvaluationForm $form): array
    {
        return array_values($form->stepEvaluationForms()
            ->with('formPhaseDetail.formPhase:id,title')
            ->get()
            ->map(fn (StepEvaluationForm $step): array => [
                'phaseId' => $step->formPhaseDetail->form_phase_id,
                'phaseTitle' => $step->formPhaseDetail->formPhase->title ?? '',
                'stepLabel' => $step->formPhaseDetail->deadline_type->stepLabel($step->formPhaseDetail->deadline_cycle),
            ])
            ->sortBy([['phaseTitle', 'asc'], ['phaseId', 'asc']])
            ->values()
            ->all());
    }
}
