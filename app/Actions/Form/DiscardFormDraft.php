<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
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
        $draft = $form->draft()->first();

        if ($draft === null) {
            return;
        }

        $draft->delete();
        $form->unsetRelation('draft');
        AuditTrail::record('Draf formulir dibuang', $form, $actor, new AuditChange(old: ['fields' => count($draft->payload['fields'])]));
    }
}
