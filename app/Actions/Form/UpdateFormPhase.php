<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseInput;
use App\Models\FormPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-33 — judul, deskripsi, dan status aktif tahap bukan isi berversi, jadi disunting langsung;
 * langkahnya lewat draf (SavePhaseDraft).
 *
 * @method static void run(FormPhase $phase, FormPhaseInput $input, User $actor)
 */
class UpdateFormPhase
{
    use AsAction;

    public function handle(FormPhase $phase, FormPhaseInput $input, User $actor): void
    {
        DB::transaction(function () use ($phase, $input, $actor): void {
            $old = $phase->only(['title', 'description', 'is_active']);

            $phase->update([
                'title' => $input->title,
                'description' => $input->description,
                'is_active' => $input->isActive,
            ]);

            AuditTrail::record('Tahap formulir diperbarui', $phase, $actor, new AuditChange(
                old: $old,
                new: $phase->only(['title', 'description', 'is_active']),
            ));
        });
    }
}
