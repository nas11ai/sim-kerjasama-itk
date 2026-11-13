<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Models\ReviewEvaluationForm;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-30 — pemasangan hanya Formulir Evaluasi terbit, sekali per langkah; langkah berform evaluasi
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
        $forms = ReviewEvaluationForm::query()->whereIn('id', $requested)->withExists('versions')->get(['id']);
        $existing = array_values($forms->map(fn (ReviewEvaluationForm $form): int => $form->id)->all());
        $published = array_values($forms->filter(fn (ReviewEvaluationForm $form): bool => $form->versions_exists === true)
            ->map(fn (ReviewEvaluationForm $form): int => $form->id)
            ->all());

        foreach ($details as $index => $detail) {
            $this->assertStepEvaluationForms($index, $detail, $existing, $published);
        }
    }

    /**
     * @param  list<int>  $existing
     * @param  list<int>  $published
     *
     * @throws ValidationException
     */
    private function assertStepEvaluationForms(int $index, FormPhaseDetailInput $detail, array $existing, array $published): void
    {
        $ids = array_map(fn (StepEvaluationFormInput $a): int => $a->reviewEvaluationFormId, $detail->evaluationForms);
        $active = count(array_filter($detail->evaluationForms, fn (StepEvaluationFormInput $a): bool => $a->isActive));

        if (!$detail->needsReview && $active > 0) {
            throw ValidationException::withMessages([
                "phase_details.{$index}.needs_review" => "Detail ini punya {$active} formulir evaluasi aktif, jadi tetap harus dinilai.",
            ]);
        }

        // B12 — draf tidak ber-FK, jadi Formulir Evaluasi bisa dihapus dari pustaka sesudah draf disimpan.
        if (array_diff($ids, $existing) !== []) {
            throw ValidationException::withMessages([
                "phase_details.{$index}.evaluation_forms" => 'Formulir evaluasi yang terpasang di langkah ini sudah dihapus dari pustaka. Lepaskan lalu simpan draf.',
            ]);
        }

        if (array_diff($ids, $published) !== []) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Formulir evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang.',
            ]);
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Formulir evaluasi ini sudah terpasang di langkah ini.',
            ]);
        }
    }
}
