<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\EvaluationFormCardData;
use App\Models\ReviewEvaluationForm;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pustaka Form Evaluasi: semua form urut judul lalu id, beserta versi terbit terakhir, ada-tidaknya
 * draf (BR-FE-31), jumlah langkah pemasangnya, dan jumlah penugasannya — tanpa kueri per kartu.
 *
 * @method static list<EvaluationFormCardData> run()
 */
class EvaluationFormLibrary implements ChangesNoState
{
    use AsAction;

    /** @return list<EvaluationFormCardData> */
    public function handle(): array
    {
        return array_values(ReviewEvaluationForm::query()
            ->with('latestVersion')
            ->withExists('draft')
            ->withCount(['stepEvaluationForms', 'reviewerFormAssignments'])
            ->orderBy('title')
            ->orderBy('id')
            ->get()
            ->map(fn (ReviewEvaluationForm $form): EvaluationFormCardData => new EvaluationFormCardData(
                id: $form->id,
                title: $form->title,
                latestVersionLabel: $form->latestVersion?->label(),
                hasDraft: $form->draft_exists === true,
                stepCount: $form->step_evaluation_forms_count ?? 0,
                assignmentCount: $form->reviewer_form_assignments_count ?? 0,
            ))
            ->all());
    }
}
