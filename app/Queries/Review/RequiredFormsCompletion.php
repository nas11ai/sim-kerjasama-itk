<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\SubmissionReviewer;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-05 — apakah seluruh form wajib reviewer ini sudah tersubmit. Tanpa form
 * wajib jawabannya "sudah": menunggu yang tidak ada akan mengunci siklus
 * berikutnya selamanya.
 *
 * @method static bool run(SubmissionReviewer $reviewer)
 */
class RequiredFormsCompletion implements ChangesNoState
{
    use AsAction;

    public function handle(SubmissionReviewer $reviewer): bool
    {
        return $reviewer->reviewerFormAssignments()
            ->required()
            ->active()
            ->whereDoesntHave('reviewFormResponse', function (Builder $query): void {
                $query->where('status', 'submitted');
            })
            ->doesntExist();
    }
}
