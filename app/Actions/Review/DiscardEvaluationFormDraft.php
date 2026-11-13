<?php

namespace App\Actions\Review;

use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-31 — membuang draf Formulir Evaluasi tanpa meninggalkan versi. Tanpa draf, tidak ada yang
 * dibuang dan tidak ada audit.
 *
 * @method static void run(ReviewEvaluationForm $form, User $actor)
 */
class DiscardEvaluationFormDraft
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form, User $actor): void
    {
        $draft = $form->draft()->first();

        if ($draft === null) {
            return;
        }

        $draft->delete();
        $form->unsetRelation('draft');
        AuditTrail::record('Draf form evaluasi dibuang', $form, $actor, new AuditChange(old: $draft->payload));
    }
}
