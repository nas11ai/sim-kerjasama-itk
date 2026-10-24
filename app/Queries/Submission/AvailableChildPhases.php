<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\States\Submission\Approved;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fase yang masih boleh melahirkan laporan turunan untuk pengajuan ini. Kosong selama induknya
 * belum APPROVED (BR-FE-04), dan menyusut setiap kali satu laporan dibuat — satu laporan per
 * (induk, fase) (BR-MON-03).
 *
 * @method static list<array{id: int, title: string}> run(FormSubmission $submission)
 */
class AvailableChildPhases implements ChangesNoState
{
    use AsAction;

    /** @return list<array{id: int, title: string}> */
    public function handle(FormSubmission $submission): array
    {
        if (!$submission->status instanceof Approved || $submission->parent_submission_id !== null) {
            return [];
        }

        $taken = FormSubmission::query()
            ->where('parent_submission_id', $submission->id)
            ->pluck('form_phase_detail_id');

        return array_values(FormPhaseDetail::query()
            ->whereHas('formAccessControl', fn (Builder $q) => $q->where('form_id', $submission->form_id))
            ->whereNotIn('id', $taken)
            ->where('id', '!=', $submission->form_phase_detail_id)
            ->with('formPhase')
            ->ordered()
            ->get()
            ->map(fn (FormPhaseDetail $detail): array => [
                'id' => $detail->id,
                'title' => $detail->formPhase instanceof FormPhase ? $detail->formPhase->title : 'Fase #'.$detail->id,
            ])
            ->all());
    }
}
