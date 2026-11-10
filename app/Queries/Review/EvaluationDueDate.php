<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tenggat bawaan penugasan yang dimulai reviewer sendiri: Batas Evaluasi langkah pengajuannya di
 * kalender periode pengajuan (BR-FE-26), atau seminggu lagi bila kalender itu tak punya. Bukan tanggal terakhir periode:
 * tanggal itu milik tahap lain (mis. Monev), jadi reviewer mendapat jendela yang keliru.
 *
 * @method static Carbon run(FormSubmission $submission)
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): Carbon
    {
        $step = $submission->formPhaseDetail;
        $due = $step === null ? null : $submission->submissionPeriod?->deadlineFor($step, DeadlineType::Evaluation);

        return $due->datetime ?? now()->addWeek();
    }
}
