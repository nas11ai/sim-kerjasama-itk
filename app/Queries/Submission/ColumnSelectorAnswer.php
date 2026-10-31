<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jawaban field pemilih kolom (`scheme_selector`, `trl_selector`) sebuah pengajuan, sebelum
 * dipindah ke kolomnya (ADR 0005). Null bila Form tidak punya field bertipe itu.
 *
 * @method static array{field: FormField, value: string|null}|null run(FormSubmission $submission, string $type)
 */
class ColumnSelectorAnswer implements ChangesNoState
{
    use AsAction;

    /** @return array{field: FormField, value: string|null}|null */
    public function handle(FormSubmission $submission, string $type): ?array
    {
        $field = FormField::query()
            ->where('form_id', $submission->form_id)
            ->whereHas('fieldType', fn (Builder $q) => $q->where('name', $type))
            ->first();

        if ($field === null) {
            return null;
        }

        $value = FormFieldResponse::query()
            ->where('form_submission_id', $submission->id)
            ->where('form_field_id', $field->id)
            ->value('value');

        return ['field' => $field, 'value' => is_string($value) && $value !== '' ? $value : null];
    }
}
