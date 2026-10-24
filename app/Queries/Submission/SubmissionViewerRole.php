<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Peran penonton atas satu pengajuan, untuk layar detail, beserta penugasan reviewernya yang
 * masih berlaku (BR-REV-10). Penugasan dicari lewat semua record reviewer milik user.
 *
 * @method static array{role: 'admin'|'submitter'|'reviewer'|'user', assignment: SubmissionReviewer|null} run(FormSubmission $submission, User $user)
 */
class SubmissionViewerRole implements ChangesNoState
{
    use AsAction;

    /** @return array{role: 'admin'|'submitter'|'reviewer'|'user', assignment: SubmissionReviewer|null} */
    public function handle(FormSubmission $submission, User $user): array
    {
        $assignment = SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->where('status', 'active')
            ->whereHas('reviewer', fn (Builder $q) => $q->where('user_id', $user->id))
            ->first();

        return ['role' => $this->role($submission, $user, $assignment), 'assignment' => $assignment];
    }

    /** @return 'admin'|'submitter'|'reviewer'|'user' */
    private function role(FormSubmission $submission, User $user, ?SubmissionReviewer $assignment): string
    {
        if ($user->can('users.manage')) {
            return 'admin';
        }

        if ($submission->submitted_by === $user->id) {
            return 'submitter';
        }

        return $assignment === null ? 'user' : 'reviewer';
    }
}
