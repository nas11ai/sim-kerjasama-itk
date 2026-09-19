<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use DateTime;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tenggat evaluasi sebuah pengajuan: tanggal terakhir di periodenya.
 * Period diturunkan lewat FK submission_period_id, bukan ditebak lewat
 * submission_period_phases — pivot itu tanpa unique constraint (ADR 0004).
 *
 * @method static DateTime|null run(FormSubmission $submission)
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): ?DateTime
    {
        $period = $submission->submissionPeriod;

        if ($period === null) {
            return null;
        }

        return $period->submissionDates()->orderByDesc('datetime')->first()?->datetime;
    }
}
