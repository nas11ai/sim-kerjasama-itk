<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-21 — rujukan yang menghalangi penghapusan satu periode. Dipakai hook
 * `deleting` dan Action-nya supaya keduanya menghitung hal yang sama.
 *
 * @method static array<string, int> run(SubmissionPeriod $period)
 */
class SubmissionPeriodReferences implements ChangesNoState
{
    use AsAction;

    /** @return array<string, int> */
    public function handle(SubmissionPeriod $period): array
    {
        return [
            'pengajuan' => FormSubmission::query()
                ->where('submission_period_id', $period->id)
                ->count(),
            'detail tahap yang memakai tanggalnya' => FormPhaseDetail::query()
                ->whereIn('submission_date_id', SubmissionDate::query()
                    ->where('submission_period_id', $period->id)
                    ->select('id'))
                ->count(),
        ];
    }
}
