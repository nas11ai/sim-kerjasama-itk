<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Form\PhaseVersionFor;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * C1 — detail fase langkah 1 wizard dari form_phase_id + form_id di versi struktur angkatan (BR-FE-32).
 * Satu Form bisa punya >1 FormAccessControl (satu per organisasi), jadi ikut di-scope accessibleBy()
 * (ADR 0004) — null juga berarti user tak berwenang atasnya.
 *
 * @method static FormPhaseDetail|null run(int $formPhaseId, int $formId, int $submissionPeriodId, User $user)
 */
class AccessibleFormPhaseDetail implements ChangesNoState
{
    use AsAction;

    public function handle(int $formPhaseId, int $formId, int $submissionPeriodId, User $user): ?FormPhaseDetail
    {
        $phase = FormPhase::query()->find($formPhaseId);
        $period = SubmissionPeriod::query()->find($submissionPeriodId);
        $version = $phase === null || $period === null ? null : PhaseVersionFor::run($period, $phase);

        if ($version === null) {
            return null;
        }

        return FormPhaseDetail::query()
            ->where('form_phase_version_id', $version->id)
            ->whereIn('form_access_control_id', FormAccessControl::query()
                ->where('form_id', $formId)
                ->accessibleBy($user)
                ->select('id'))
            // BR-MON-01 — padanan SQL FormPhaseDetail::opensSubmission() (BR-FE-07): laporan monev
            // dan Upload Luaran tidak pernah dipilih sebagai detail pengajuan baru.
            ->where('deadline_type', DeadlineType::Submit->value)
            ->whereNull('deadline_cycle')
            ->orderBy('order')
            ->first();
    }
}
