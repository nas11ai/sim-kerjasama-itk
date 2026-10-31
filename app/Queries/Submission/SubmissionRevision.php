<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Data\View\Submission\Revision\ReviewThreadData;
use App\Data\View\Submission\Revision\RevisionSubmissionData;
use App\Data\View\Submission\Wizard\WizardMissingFieldData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Review\ReviewThreads;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * RESEARCHER-02-05 — ulir reviewer dan penghalang kirim ulang. Akses (BR-SM-07) dan status
 * NEEDS_REVISION dijaga controller sebelum Query ini dipanggil.
 *
 * @method static array{submission: RevisionSubmissionData, threads: list<ReviewThreadData>, missingFields: list<WizardMissingFieldData>, accountBlock: string|null, can: SubmissionAbilitiesData} run(User $viewer, FormSubmission $submission)
 */
class SubmissionRevision implements ChangesNoState
{
    use AsAction;

    /** @return array{submission: RevisionSubmissionData, threads: list<ReviewThreadData>, missingFields: list<WizardMissingFieldData>, accountBlock: string|null, can: SubmissionAbilitiesData} */
    public function handle(User $viewer, FormSubmission $submission): array
    {
        $submission->loadMissing('form:id,title');
        $threads = ReviewThreads::run($submission);
        // BR-SM-02, SM-SUBMIT-19 — penghalang yang sama dengan yang diperiksa ResubmitFormSubmission.
        $review = WizardReviewSummary::run($viewer, $submission);

        return [
            'submission' => new RevisionSubmissionData($submission->id, $submission->form->title, SubmissionStatusData::of($submission)),
            'threads' => ReviewThreadData::many($threads['summaries'], $threads['comments']),
            'missingFields' => $review->missingFields,
            'accountBlock' => $review->accountBlock,
            'can' => SubmissionAbilitiesData::forUser($viewer, $submission),
        ];
    }
}
