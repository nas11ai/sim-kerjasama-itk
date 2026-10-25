<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\SubmissionAnswerData;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jawaban form siap baca: id opsi diganti labelnya di sini supaya layar tidak menyimpan
 * logika per tipe field. Field pemilih kolom tidak punya jawaban (BR-SCH-09).
 *
 * @method static list<SubmissionAnswerData> run(FormSubmission $submission)
 */
class ReadableSubmissionAnswers implements ChangesNoState
{
    use AsAction;

    private const COLUMN_BACKED_TYPES = ['scheme_selector', 'trl_selector'];

    private const OPTION_TYPES = ['select', 'radio'];

    /** @return list<SubmissionAnswerData> */
    public function handle(FormSubmission $submission): array
    {
        $values = SubmissionAnswers::run($submission);
        $answers = [];

        foreach ($submission->form->formFields as $field) {
            $type = $field->fieldType?->name;

            if (in_array($type, self::COLUMN_BACKED_TYPES, true)) {
                continue;
            }

            $answers[] = new SubmissionAnswerData(
                label: $field->label,
                value: $this->readable($field, $type, $values[$field->id] ?? null),
                isFile: $type === 'file',
            );
        }

        return $answers;
    }

    private function readable(FormField $field, ?string $type, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($type === 'checkbox') {
            return in_array($value, ['1', 'true'], true) ? 'Ya' : 'Tidak';
        }

        if (!in_array($type, self::OPTION_TYPES, true)) {
            return $value;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);
        $option = $field->formFieldOptions->first(fn (FormFieldOption $o): bool => $o->id === $id);

        return $option->label ?? $value;
    }
}
