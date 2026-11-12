<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use DateTime;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-17 + BR-FE-26 — tenggat evaluasi = Batas Evaluasi pada siklus langkah pengajuan yang
 * dinilai, di kalender periode **pengajuan yang dinilai** — bukan Batas Submit peneliti, bukan tanggal
 * terakhir periode, dan bukan periode lain yang kebetulan menautkan fase yang sama (ADR 0019).
 *
 * @method static DateTime|null run(FormSubmission $submission)
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): ?DateTime
    {
        $step = $submission->formPhaseDetail;

        if ($step === null) {
            return null;
        }

        return $submission->submissionPeriod?->deadlineFor($step, DeadlineType::Evaluation)?->datetime;
    }
}
