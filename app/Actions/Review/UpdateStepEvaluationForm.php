<?php

namespace App\Actions\Review;

use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Wajib dan aktif sebuah pemasangan (G2-W2). Menonaktifkan berhenti menawarkan form ini untuk
 * pekerjaan baru di langkah itu (BR-FE-22), tanpa menyentuh penugasan yang sudah ada.
 *
 * @method static void run(StepEvaluationForm $step, bool $isRequired, bool $isActive, User $actor)
 */
class UpdateStepEvaluationForm
{
    use AsAction;

    public function handle(StepEvaluationForm $step, bool $isRequired, bool $isActive, User $actor): void
    {
        $old = ['is_required' => $step->is_required, 'is_active' => $step->is_active];
        $step->update(['is_required' => $isRequired, 'is_active' => $isActive]);

        if (!$step->wasChanged()) {
            return;
        }

        AuditTrail::record('Pemasangan form evaluasi diubah', $step, $actor, new AuditChange($old, [
            'is_required' => $isRequired,
            'is_active' => $isActive,
        ]));
    }
}
