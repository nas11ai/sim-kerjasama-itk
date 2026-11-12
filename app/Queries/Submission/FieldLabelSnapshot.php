<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-35 — teks pertanyaan dan label opsi Form sebuah pengajuan saat ini. Opsi yang sudah
 * dihapus ikut, karena jawaban lama masih menunjuk id-nya (BR-FE-24); field pemilih kolom
 * tidak punya jawaban, jadi tidak ikut (BR-SCH-09).
 *
 * @phpstan-type FieldLabel array{form_field_id: int, type: string, label: string, options: list<array{id: int, label: string}>}
 *
 * @method static list<FieldLabel> run(FormSubmission $submission)
 */
class FieldLabelSnapshot implements ChangesNoState
{
    use AsAction;

    private const COLUMN_BACKED_TYPES = ['scheme_selector', 'trl_selector'];

    /** @return list<FieldLabel> */
    public function handle(FormSubmission $submission): array
    {
        $fields = FormField::query()
            ->where('form_id', $submission->form_id)
            ->with('fieldType:id,name')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $options = [];
        $stored = FormFieldOption::withTrashed()->whereIn('form_field_id', $fields->modelKeys())->orderBy('id')->get();

        foreach ($stored as $option) {
            $options[$option->form_field_id][] = ['id' => $option->id, 'label' => $option->label];
        }

        $labels = [];

        foreach ($fields as $field) {
            $type = $field->fieldType->name ?? '';

            if (in_array($type, self::COLUMN_BACKED_TYPES, true)) {
                continue;
            }

            $labels[] = [
                'form_field_id' => $field->id,
                'type' => $type,
                'label' => $field->label,
                'options' => $options[$field->id] ?? [],
            ];
        }

        return $labels;
    }
}
