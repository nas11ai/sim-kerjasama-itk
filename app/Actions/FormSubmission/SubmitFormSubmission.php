<?php
namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;
use App\SubmissionStatus;

class SubmitFormSubmission
{
    use AsAction;

    public function handle(FormSubmission $submission, User $user): FormSubmission
    {
        if ($submission->submitted_by !== $user->id) {
            throw new \Exception('Unauthorized');
        }

        $submission->update([
            'is_submitted' => true,
            'status' => SubmissionStatus::PENDING,
        ]);

        return $submission->fresh();
    }
}
