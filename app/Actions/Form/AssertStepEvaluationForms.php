<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Models\ReviewEvaluationForm;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-30 — pemasangan hanya Form Evaluasi terbit, sekali per langkah; langkah berform evaluasi
 * aktif tetap direview. Dijalankan saat simpan draf dan lagi saat terbit (BR-FE-33). Hanya
 * membaca dan menolak, **tanpa audit (BR-RPT-09).**
 *
 * @method static void run(list<FormPhaseDetailInput> $details)
 */
class AssertStepEvaluationForms implements ChangesNoState
{
    use AsAction;

    /**
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    public function handle(array $details): void
    {
        $requested = array_merge(...array_map(
            fn (FormPhaseDetailInput $d): array => array_map(fn (StepEvaluationFormInput $a): int => $a->reviewEvaluationFormId, $d->evaluationForms),
            $details,
        ));
        $published = array_values(ReviewEvaluationForm::query()->whereIn('id', $requested)->whereHas('versions')->get(['id'])
            ->map(fn (ReviewEvaluationForm $form): int => $form->id)
            ->all());

        foreach ($details as $index => $detail) {
            $this->assertStepEvaluationForms($index, $detail, $published);
        }
    }

    /**
     * @param  list<int>  $published
     *
     * @throws ValidationException
     */
    private function assertStepEvaluationForms(int $index, FormPhaseDetailInput $detail, array $published): void
    {
        $ids = array_map(fn (StepEvaluationFormInput $a): int => $a->reviewEvaluationFormId, $detail->evaluationForms);
        $active = count(array_filter($detail->evaluationForms, fn (StepEvaluationFormInput $a): bool => $a->isActive));

        if (!$detail->needsReview && $active > 0) {
            throw ValidationException::withMessages([
                "phase_details.{$index}.needs_review" => "Detail ini punya {$active} form evaluasi aktif, jadi tetap harus direview.",
            ]);
        }

        if (array_diff($ids, $published) !== []) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang.',
            ]);
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Form evaluasi ini sudah terpasang di langkah ini.',
            ]);
        }
    }
}
