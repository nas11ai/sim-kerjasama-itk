<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\Form\AssertFormAccessible;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi layar biodata: form aktif beserta field-nya, submission biodata terakhir milik user,
 * dan apakah ia masih boleh menyuntingnya. Null kalau tidak ada form biodata yang aktif.
 *
 * @phpstan-type FieldView array{id: int, label: string, is_required: bool, order: int, field_type: array{id: int|null, name: string|null}, form_field_options: array<int, array{id: int, label: string}>}
 * @phpstan-type FormView array{id: int, title: string, description: string|null, form_fields: array<int, FieldView>}
 * @phpstan-type SubmissionView array{id: int, is_submitted: bool, status: string|null, created_at: string|null, updated_at: string|null}
 * @phpstan-type BiodataView array{form: FormView, existingSubmission: SubmissionView|null, existingResponses: array<int, string|null>, canEdit: bool}
 *
 * @method static BiodataView|null run(User $user)
 */
class BiodataFormView implements ChangesNoState
{
    use AsAction;

    /**
     * @return BiodataView|null
     *
     * @throws AuthorizationException
     */
    public function handle(User $user): ?array
    {
        $form = Form::where('form_type_id', FormType::BIODATA_ID)
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return null;
        }

        AssertFormAccessible::run($user, $form, 'Anda tidak memiliki akses ke form biodata ini.');

        $form->load(['formFields.fieldType', 'formFields.formFieldOptions']);

        $submission = FormSubmission::with('formFieldResponses')
            ->where('form_id', $form->id)
            ->where('submitted_by', $user->id)
            ->latest('id')
            ->first();

        // Rejected itu final: user mulai biodata baru dari awal, bukan menyunting yang ditolak.
        $rejected = $submission?->status instanceof Rejected;

        return [
            'form' => $this->form($form),
            'existingSubmission' => $submission ? $this->submission($submission) : null,
            'existingResponses' => $rejected ? [] : $submission?->formFieldResponses
                ->mapWithKeys(fn (FormFieldResponse $response): array => [$response->form_field_id => $response->value])
                ->all() ?? [],
            'canEdit' => !$submission || $rejected || $submission->status instanceof NeedsRevision,
        ];
    }

    /** @return FormView */
    private function form(Form $form): array
    {
        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'form_fields' => $form->formFields->sortBy('order')->values()->map(fn (FormField $field): array => [
                'id' => $field->id,
                'label' => $field->label,
                'is_required' => $field->is_required,
                'order' => $field->order,
                'field_type' => ['id' => $field->fieldType?->id, 'name' => $field->fieldType?->name],
                'form_field_options' => $field->formFieldOptions
                    ->map(fn (FormFieldOption $option): array => ['id' => $option->id, 'label' => $option->label])
                    ->values()
                    ->all(),
            ])->all(),
        ];
    }

    /** @return SubmissionView */
    private function submission(FormSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'is_submitted' => $submission->is_submitted,
            'status' => $submission->status?->key(),
            'created_at' => $submission->created_at?->toISOString(),
            'updated_at' => $submission->updated_at?->toISOString(),
        ];
    }
}
