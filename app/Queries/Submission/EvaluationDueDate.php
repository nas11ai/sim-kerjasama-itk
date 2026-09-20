<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewEvaluationForm;
use DateTime;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-17 — tenggat evaluasi milik FormPhaseDetail yang memiliki form ini,
 * bukan batas submit peneliti dan bukan tanggal terakhir period. Ditempuh
 * lewat FK, jadi pivot submission_period_phases tak bisa menyeretnya (ADR 0004).
 *
 * @method static DateTime|null run(ReviewEvaluationForm $form)
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form): ?DateTime
    {
        return $form->formPhaseDetail?->submissionDate?->datetime;
    }
}
