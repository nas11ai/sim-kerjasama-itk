<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SubmitFormSubmission
{
    use AsAction;

    public function handle(FormSubmission $submission, User $user): FormSubmission
    {
        // BR-SM-04 — hanya submitted_by yang boleh submit dan resubmit.
        if ($submission->submitted_by !== $user->id) {
            throw new AuthorizationException(
                'Hanya lead researcher yang bisa submit pengajuan ini.'
            );
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — tidak bisa disubmit."
            );
        }

        $submission->update([
            'is_submitted' => true,
        ]);

        $status->transitionTo(Submitted::class);

        return $submission->refresh();
    }
}
