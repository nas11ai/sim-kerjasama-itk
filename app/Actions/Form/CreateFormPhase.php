<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseInput;
use App\Data\Request\PhaseDraftInput;
use App\Models\FormPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — membuat tahap formulir; BR-FE-33 — langkahnya lahir sebagai draf, tanpa versi, jadi
 * tahap baru belum bisa ditautkan ke periode sampai diterbitkan.
 *
 * @method static FormPhase run(FormPhaseInput $input, PhaseDraftInput $steps, User $actor)
 */
class CreateFormPhase
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhaseInput $input, PhaseDraftInput $steps, User $actor): FormPhase
    {
        return DB::transaction(function () use ($input, $steps, $actor): FormPhase {
            $phase = FormPhase::create([
                'title' => $input->title,
                'description' => $input->description,
                'is_active' => $input->isActive,
            ]);

            // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
            AuditTrail::record('Tahap formulir dibuat', $phase, $actor, new AuditChange(new: [
                'title' => $phase->title,
                'description' => $phase->description,
                'is_active' => $phase->is_active,
            ]));
            SavePhaseDraft::run($phase, $steps, $actor);

            return $phase;
        });
    }
}
