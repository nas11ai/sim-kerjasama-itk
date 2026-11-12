<?php

namespace App\Actions\Form;

use App\Data\Request\PhaseDraftInput;
use App\Models\FormPhase;
use App\Models\PhaseDraft;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-33 — menyimpan struktur tahap sebagai draf (satu per tahap, ditimpa tiap simpan). Draf
 * tidak berlaku bagi angkatan mana pun. Aturan langkah dijalankan di sini juga supaya galatnya
 * terlihat saat simpan; Terbitkan memvalidasi ulang seluruhnya.
 *
 * @method static PhaseDraft run(FormPhase $phase, PhaseDraftInput $input, User $actor)
 */
class SavePhaseDraft
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhase $phase, PhaseDraftInput $input, User $actor): PhaseDraft
    {
        AssertPhaseSteps::run($input->steps);
        AssertStepEvaluationForms::run($input->steps);

        return DB::transaction(function () use ($phase, $input, $actor): PhaseDraft {
            // B13 — Terbitkan memegang kunci ini sambil menghapus draf; tanpanya simpan yang
            // berpapasan memperbarui 0 baris tapi tetap melapor "Draf disimpan."
            FormPhase::query()->whereKey($phase->id)->lockForUpdate()->firstOrFail();
            $draft = PhaseDraft::query()->updateOrCreate(
                ['draftable_id' => $phase->id],
                ['payload' => $input->toPayload(), 'updated_by' => $actor->id],
            );
            $phase->unsetRelation('draft');

            AuditTrail::record('Draf tahap disimpan', $phase, $actor, new AuditChange(new: ['steps' => count($input->steps)]));

            return $draft;
        });
    }
}
