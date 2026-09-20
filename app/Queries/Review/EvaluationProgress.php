<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\EvaluationProgressData;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sebaran status evaluasi reviewer yang masih aktif (BR-REV-05): satu kueri,
 * bukan lima COUNT. countBy() di PHP, bukan GROUP BY, karena hitungannya
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
            ->where('status', 'active')
            ->get(['evaluation_status'])
            ->countBy('evaluation_status')
            ->all();

        $completed = $counts['completed'] ?? 0;
        $pending = $counts['pending'] ?? 0;
        $notRequired = $counts['not_required'] ?? 0;
        $total = array_sum($counts);
        $settled = $completed + $notRequired;

        return new EvaluationProgressData(
            totalReviewers: $total,
            completed: $completed,
            pending: $pending,
            notRequired: $notRequired,
            completionPercentage: $total > 0 ? (int) round($settled / $total * 100) : 100,
            allCompleted: $settled === $total,
        );
    }
}
