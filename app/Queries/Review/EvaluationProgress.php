<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\EvaluationProgressData;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sebaran status evaluasi sebuah pengajuan: satu kueri, bukan lima COUNT
 * seperti versi lama. countBy() di PHP, bukan GROUP BY, karena hitungannya
 * bertipe int tanpa cast — reviewer per pengajuan cuma segelintir.
 *
 * @method static EvaluationProgressData run(FormSubmission $submission)
 */
class EvaluationProgress implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): EvaluationProgressData
    {
        $counts = $submission->submissionReviewers()
            ->get(['evaluation_status'])
            ->countBy('evaluation_status')
            ->all();

        $completed = $counts['completed'] ?? 0;
        $pending = $counts['pending'] ?? 0;
        $notRequired = $counts['not_required'] ?? 0;
        $total = array_sum($counts);

        return new EvaluationProgressData(
            totalReviewers: $total,
            completed: $completed,
            pending: $pending,
            notRequired: $notRequired,
            completionPercentage: $total > 0 ? (int) round((($completed + $notRequired) / $total) * 100) : 100,
            allCompleted: $pending === 0,
        );
    }
}
