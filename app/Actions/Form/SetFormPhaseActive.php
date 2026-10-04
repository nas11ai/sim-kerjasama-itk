<?php

namespace App\Actions\Form;

use App\Models\FormPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mengaktifkan atau menonaktifkan tahap formulir. Menonaktifkan adalah jalan yang
 * dianjurkan penolakan hapus BR-FE-20, jadi perubahannya harus berjejak (BR-RPT-09).
 *
 * @method static FormPhase run(FormPhase $phase, bool $isActive, User $actor)
 */
class SetFormPhaseActive
{
    use AsAction;

    public function handle(FormPhase $phase, bool $isActive, User $actor): FormPhase
    {
        return DB::transaction(function () use ($phase, $isActive, $actor): FormPhase {
            $old = ['is_active' => $phase->is_active];

            $phase->update(['is_active' => $isActive]);

            AuditTrail::record(
                'Status tahap formulir diubah',
                $phase,
                $actor,
                new AuditChange(old: $old, new: ['is_active' => $phase->is_active]),
            );

            return $phase;
        });
    }
}
