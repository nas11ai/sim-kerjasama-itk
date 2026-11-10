<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use DateTime;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-17 + BR-FE-26 — tenggat evaluasi = Batas Evaluasi pada siklus langkah yang memiliki form
 * ini, di kalender periode **pengajuan yang dinilai** — bukan Batas Submit peneliti, bukan tanggal
 * terakhir periode, dan bukan periode lain yang kebetulan menautkan fase yang sama (ADR 0019).
 *
 * @method static DateTime|null run(ReviewEvaluationForm $form, FormSubmission $submission)
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form, FormSubmission $submission): ?DateTime
    {
        $step = $form->formPhaseDetail;

        if ($step === null) {
            return null;
        }

        return $submission->submissionPeriod?->deadlineFor($step, DeadlineType::Evaluation)?->datetime;
    }
}
