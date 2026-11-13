<?php

namespace App\Actions\Review;

use App\Data\Request\EvaluationFormDetailsInput;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Formulir Evaluasi baru di pustaka, belum terbit dan belum terpasang di langkah mana pun.
 *
 * @method static ReviewEvaluationForm run(EvaluationFormDetailsInput $input, User $actor)
 */
class CreateEvaluationForm
{
    use AsAction;

    public function handle(EvaluationFormDetailsInput $input, User $actor): ReviewEvaluationForm
    {
        $form = ReviewEvaluationForm::create($input->attributes());
        AuditTrail::record('Form evaluasi dibuat', $form, $actor, new AuditChange(new: $form->auditSnapshot()));

        return $form;
    }
}
