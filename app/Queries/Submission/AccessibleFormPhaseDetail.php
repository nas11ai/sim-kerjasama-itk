<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * C1 — detail fase langkah 1 wizard dari form_phase_id + form_id. form_id saja ambigu bila dipakai >1
 * FormPhaseDetail (ADR 0004); pasangannya masih ambigu bila satu Form punya >1 FormAccessControl (satu
 * per organisasi), jadi ikut di-scope accessibleBy() — null juga berarti user tak berwenang atasnya.
 *
 * @method static FormPhaseDetail|null run(int $formPhaseId, int $formId, int $submissionPeriodId, User $user)
 */
class AccessibleFormPhaseDetail implements ChangesNoState
{
    use AsAction;

    public function handle(int $formPhaseId, int $formId, int $submissionPeriodId, User $user): ?FormPhaseDetail
    {
        return FormPhaseDetail::query()
            ->where('form_phase_id', $formPhaseId)
            ->whereIn('form_access_control_id', FormAccessControl::query()
                ->where('form_id', $formId)
                ->accessibleBy($user)
                ->select('id'))
            // BR-MON-01 — padanan SQL FormPhaseDetail::opensSubmission(): laporan monev dan Upload
            // Luaran tidak pernah dipilih sebagai detail pengajuan baru.
            ->whereHas('submissionDate', fn (Builder $date) => $date
                ->where('type', DeadlineType::Submit->value)
                ->whereNull('cycle'))
            // BR-FE-17 / K9b — detail yang tenggatnya milik periode tujuan didahulukan, lalu urutan.
            ->orderByRaw(
                'CASE WHEN (SELECT submission_period_id FROM submission_dates WHERE submission_dates.id = form_phase_details.submission_date_id) = ? THEN 0 ELSE 1 END',
                [$submissionPeriodId],
            )
            ->orderBy('order')
            ->first();
    }
}
