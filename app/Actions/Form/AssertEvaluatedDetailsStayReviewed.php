<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Models\FormPhaseDetail;
use App\Models\StepEvaluationForm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W97 — form evaluasi "attached ke FormPhaseDetail yang `needs_review = true`" (ubiquitous
 * language), jadi detail dengan form evaluasi aktif tidak bisa dimatikan review-nya. Cermin
 * AssertReviewedPhaseDetail. Hanya membaca dan menolak, **tidak menulis audit (BR-RPT-09).**
 *
 * @method static void run(Collection<int, FormPhaseDetail> $existing, list<FormPhaseDetailInput> $details)
 */
class AssertEvaluatedDetailsStayReviewed implements ChangesNoState
{
    use AsAction;

    /**
     * @param  Collection<int, FormPhaseDetail>  $existing  detail lama tahap, ber-key id
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    public function handle(Collection $existing, array $details): void
    {
        $active = $this->activeCounts($existing);
        $errors = [];

        foreach ($details as $index => $detail) {
            $count = $detail->id === null ? 0 : ($active[$detail->id] ?? 0);

            if ($detail->needsReview || $count === 0) {
                continue;
            }

            $errors["phase_details.{$index}.needs_review"] = "Detail ini punya {$count} form evaluasi aktif, jadi tetap harus direview.";
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Collection<int, FormPhaseDetail>  $existing
     * @return array<int, int> jumlah form evaluasi aktif per id detail
     */
    private function activeCounts(Collection $existing): array
    {
        $counts = [];
        $forms = StepEvaluationForm::query()
            ->active()
            ->whereIn('form_phase_detail_id', $existing->modelKeys())
            ->get(['form_phase_detail_id']);

        foreach ($forms as $form) {
            $counts[$form->form_phase_detail_id] = ($counts[$form->form_phase_detail_id] ?? 0) + 1;
        }

        return $counts;
    }
}
