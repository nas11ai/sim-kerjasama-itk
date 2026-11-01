<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Manage\ManagedReviewerData;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Semua penugasan reviewer sebuah pengajuan bagi Operator: yang aktif dulu, lalu yang
 * digantikan sebagai sejarah (BR-REV-10, REV-REASSIGN-01).
 *
 * @method static list<ManagedReviewerData> run(FormSubmission $submission)
 */
class ManagedReviewers implements ChangesNoState
{
    use AsAction;

    /** @return list<ManagedReviewerData> */
    public function handle(FormSubmission $submission): array
    {
        return array_values($submission->submissionReviewers()
            ->with('reviewer.user.userProfile:id,user_id,nidn')
            // Urutan teks: 'active' < 'replaced', jadi yang aktif selalu di atas.
            ->orderBy('status')
            ->orderBy('id')
            ->get()
            ->map(fn (SubmissionReviewer $seat): ManagedReviewerData => ManagedReviewerData::fromModel($seat))
            ->all());
    }
}
