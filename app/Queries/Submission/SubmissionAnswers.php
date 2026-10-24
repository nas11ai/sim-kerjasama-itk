<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi pengajuan untuk layar detail: field form terurut beserta jawabannya per field.
 * Relasi form dan pengaju ikut dimuat ke `$submission` karena layar membacanya dari sana.
 *
 * @method static array<int, string|null> run(FormSubmission $submission)
 */
class SubmissionAnswers implements ChangesNoState
{
    use AsAction;

    /** @return array<int, string|null> form_field_id => nilai */
    public function handle(FormSubmission $submission): array
    {
        $submission->load([
            'form.formFields' => fn (Relation $q) => $q->orderBy('order'),
            'form.formFields.fieldType',
            'form.formFields.formFieldOptions',
            'formFieldResponses',
            'submittedBy:id,name,email',
        ]);

        return $submission->formFieldResponses
            ->mapWithKeys(fn (FormFieldResponse $response): array => [$response->form_field_id => $response->value])
            ->all();
    }
}
