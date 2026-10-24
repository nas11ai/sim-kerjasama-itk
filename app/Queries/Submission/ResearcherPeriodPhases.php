<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fase sebuah periode yang terbuka bagi peneliti, dengan pengajuannya per detail fase. Yang
 * dicocokkan detail fase dan periode pengajuan itu sendiri, bukan form-nya: form dipakai ulang
 * lintas periode.
 *
 * @phpstan-type SubmissionRow array{id: int, form: Form|null, is_submitted: bool, can_proceed: bool, submitted_at: null, created_at: CarbonInterface|null, updated_at: CarbonInterface|null}
 *
 * @method static Collection<int, FormPhase> run(SubmissionPeriod $period, User $user)
 */
class ResearcherPeriodPhases implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, FormPhase> */
    public function handle(SubmissionPeriod $period, User $user): Collection
    {
        $accessible = FormAccessControl::query()->accessibleBy($user)->select('id');

        $phases = FormPhase::query()
            ->whereHas('submissionPeriodPhases', fn (Builder $q) => $q->where('submission_period_id', $period->id))
            ->whereHas('formPhaseDetails', fn (Builder $q) => $q->whereIn('form_access_control_id', $accessible))
            ->with(['formPhaseDetails' => fn (Relation $q) => $q->whereIn('form_access_control_id', $accessible)
                ->with('formAccessControl.form.formType')
                ->orderBy('order')])
            ->get();

        $mine = FormSubmission::query()
            ->where('submitted_by', $user->id)
            ->where('submission_period_id', $period->id)
            ->get();

        foreach ($phases as $phase) {
            $phase->setAttribute('user_submissions', $this->rowsFor($phase, $mine));
        }

        return $phases;
    }

    /**
     * @param  Collection<int, FormSubmission>  $mine
     * @return list<SubmissionRow>
     */
    private function rowsFor(FormPhase $phase, Collection $mine): array
    {
        $rows = [];

        foreach ($phase->formPhaseDetails as $detail) {
            foreach ($mine->where('form_phase_detail_id', $detail->id) as $submission) {
                $rows[] = $this->row($submission, $detail);
            }
        }

        return $rows;
    }

    /** @return SubmissionRow */
    private function row(FormSubmission $submission, FormPhaseDetail $detail): array
    {
        return [
            'id' => $submission->id,
            'form' => $detail->formAccessControl?->form,
            'is_submitted' => $submission->is_submitted,
            'can_proceed' => $submission->canProceed(),
            // I5 — form_submissions tidak punya kolom submitted_at; selalu null sejak dulu.
            // Utangnya dilacak di final-fix-report M0.
            'submitted_at' => null,
            'created_at' => $submission->created_at,
            'updated_at' => $submission->updated_at,
        ];
    }
}
