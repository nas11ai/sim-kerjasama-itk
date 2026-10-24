<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * C1 — detail fase langkah 1 wizard dari form_phase_id + form_id. form_id saja ambigu bila dipakai >1
 * FormPhaseDetail (ADR 0004); pasangannya masih ambigu bila satu Form punya >1 FormAccessControl (satu
 * per organisasi), jadi ikut di-scope accessibleBy() — null juga berarti user tak berwenang atasnya.
 *
 * @method static FormPhaseDetail|null run(int $formPhaseId, int $formId, User $user)
 */
class AccessibleFormPhaseDetail implements ChangesNoState
{
    use AsAction;

    public function handle(int $formPhaseId, int $formId, User $user): ?FormPhaseDetail
    {
        return FormPhaseDetail::query()
            ->where('form_phase_id', $formPhaseId)
            ->whereIn('form_access_control_id', FormAccessControl::query()
                ->where('form_id', $formId)
                ->accessibleBy($user)
                ->select('id'))
            ->first();
    }
}
