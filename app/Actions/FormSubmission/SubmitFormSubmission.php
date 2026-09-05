<?php

namespace App\Actions\FormSubmission;

use App\Events\ProposalSubmitted;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
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

        $this->guardRequiredDocuments($submission);

        $submission->update([
            'is_submitted' => true,
        ]);

        $status->transitionTo(Submitted::class);

        // Refresh dulu supaya listener yang membaca ulang baris melihat status
        // SUBMITTED yang sudah tersimpan, baru kemudian event di-dispatch.
        $submission->refresh();

        ProposalSubmitted::dispatch($submission);

        return $submission;
    }

    /**
     * BR-SM-02 — proposal PDF wajib ada sebelum submit. "Proposal PDF" tidak punya
     * entitas sendiri: ia FormField wajib bertipe `file` yang belum punya response
     * berisi.
     *
     * @throws ValidationException
     */
    private function guardRequiredDocuments(FormSubmission $submission): void
    {
        $requiredFileFields = $submission->form->formFields()
            ->where('is_required', true)
            ->whereHas('fieldType', function (Builder $query): void {
                $query->where('name', 'file');
            })
            ->get();

        if ($requiredFileFields->isEmpty()) {
            return;
        }

        $filled = $submission->formFieldResponses()
            ->whereIn('form_field_id', $requiredFileFields->pluck('id'))
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('form_field_id')
            ->all();

        $missing = $requiredFileFields->reject(
            fn (FormField $field): bool => in_array($field->id, $filled, true)
        );

        if ($missing->isEmpty()) {
            return;
        }

        // Error per field supaya wizard bisa menyorot baris yang salah.
        throw ValidationException::withMessages(
            $missing->mapWithKeys(fn (FormField $field): array => [
                'field_'.$field->id => "Field '{$field->label}' wajib diunggah sebelum submit.",
            ])->all()
        );
    }
}
