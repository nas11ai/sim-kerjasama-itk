<?php

namespace App\Actions\Form;

use App\Models\FormPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-33 — membuang draf tahap tanpa meninggalkan versi. Tanpa draf, tidak ada yang dibuang
 * dan tidak ada audit.
 *
 * @method static void run(FormPhase $phase, User $actor)
 */
class DiscardPhaseDraft
{
    use AsAction;

    public function handle(FormPhase $phase, User $actor): void
    {
        DB::transaction(function () use ($phase, $actor): void {
            // B13 — draf yang baru dipakai Terbitkan sudah terhapus saat kunci ini didapat, jadi
            // tidak diaudit sebagai dibuang.
            FormPhase::query()->whereKey($phase->id)->lockForUpdate()->firstOrFail();
            $draft = $phase->draft()->first();

            if ($draft === null) {
                return;
            }

            $draft->delete();
            $phase->unsetRelation('draft');
            AuditTrail::record('Draf tahap dibuang', $phase, $actor, new AuditChange(old: ['steps' => count($draft->payload['steps'])]));
        });
    }
}
