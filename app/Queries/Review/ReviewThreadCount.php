<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jumlah ulir review sebuah pengajuan pada satu status.
 *
 * @method static int run(FormSubmission $submission, string $status)
 */
class ReviewThreadCount implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission, string $status): int
    {
        return $submission->reviewSummaries()->where('status', $status)->count();
    }
}
