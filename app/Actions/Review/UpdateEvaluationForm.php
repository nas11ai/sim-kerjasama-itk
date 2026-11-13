<?php

namespace App\Actions\Review;

use App\Data\Request\EvaluationFormDetailsInput;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Nama pustaka Formulir Evaluasi — judul dan deskripsi — disunting langsung: keduanya tidak berversi
 * dan tidak lewat draf (BR-FE-31).
 *
 * @method static void run(ReviewEvaluationForm $form, EvaluationFormDetailsInput $input, User $actor)
 */
class UpdateEvaluationForm
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form, EvaluationFormDetailsInput $input, User $actor): void
    {
        $old = $form->auditSnapshot();
        $form->update($input->attributes());

        if (!$form->wasChanged()) {
            return;
        }

        AuditTrail::record('Form evaluasi diubah', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
    }
}
