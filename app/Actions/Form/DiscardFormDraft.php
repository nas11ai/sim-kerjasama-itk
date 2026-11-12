<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-34 — membuang draf field Form; field terbit tidak disentuh. Tanpa draf, tidak ada yang
 * dibuang dan tidak ada audit.
 *
 * @method static void run(Form $form, User $actor)
 */
class DiscardFormDraft
{
    use AsAction;

    public function handle(Form $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            // B13 — draf yang baru dipakai Terbitkan sudah terhapus saat kunci ini didapat, jadi
            // tidak diaudit sebagai dibuang.
            Form::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $draft = $form->draft()->first();

            if ($draft === null) {
                return;
            }

            $draft->delete();
            $form->unsetRelation('draft');
            AuditTrail::record('Draf formulir dibuang', $form, $actor, new AuditChange(old: ['fields' => count($draft->payload['fields'])]));
        });
    }
}
