<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\SubmissionAnswerData;
use App\Models\FieldType;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jawaban form siap baca: id opsi diganti labelnya di sini supaya layar tidak menyimpan
 * logika per tipe field. Field pemilih kolom tidak punya jawaban (BR-SCH-09).
 *
 * @phpstan-import-type FieldLabel from FieldLabelSnapshot
 *
 * @method static list<SubmissionAnswerData> run(FormSubmission $submission)
 */
class ReadableSubmissionAnswers implements ChangesNoState
{
    use AsAction;

    /** @return list<SubmissionAnswerData> */
    public function handle(FormSubmission $submission): array
    {
        // B17 — hanya jawabannya; field dan opsi dibaca sekali oleh labels(), atau tidak sama sekali dari rekaman.
        $submission->loadMissing('formFieldResponses');
        $values = $submission->formFieldResponses
            ->mapWithKeys(fn (FormFieldResponse $response): array => [$response->form_field_id => $response->value])
            ->all();
        $responseIds = $submission->formFieldResponses->pluck('id', 'form_field_id');
        $answers = [];

        foreach ($this->labels($submission) as $field) {
            $value = $values[$field['form_field_id']] ?? null;
            $isFile = $field['type'] === 'file';
            $hasFile = $isFile && $value !== null && $value !== '';

            $answers[] = new SubmissionAnswerData(
                label: $field['label'],
                value: $this->readable($field, $value),
                isFile: $isFile,
                // BR-SM-07 — satu-satunya jalan baca berkas: route unduh ber-scope (W15).
                downloadUrl: $hasFile ? route('user.submissions.files.show', [$submission->id, $responseIds[$field['form_field_id']]]) : null,
            );
        }

        return $answers;
    }

    /**
     * BR-FE-35 — rekaman teks dibaca selama pengisi tidak bisa menyunting; draf, revisi, dan
     * pengajuan tanpa rekaman memakai teks terkini.
     *
     * @return list<FieldLabel>
     */
    private function labels(FormSubmission $submission): array
    {
        $record = $submission->submitted_field_labels;
        $editable = $submission->status instanceof Draft || $submission->status instanceof NeedsRevision;

        if ($record === null || $editable) {
            return FieldLabelSnapshot::run($submission);
        }

        return $record;
    }

    /** @param FieldLabel $field */
    private function readable(array $field, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field['type'] === 'checkbox') {
            return in_array($value, ['1', 'true'], true) ? 'Ya' : 'Tidak';
        }

        if (!in_array($field['type'], FieldType::OPTION_TYPES, true)) {
            return $value;
        }

        return $this->optionLabel($field['options'], $value) ?? $value;
    }

    /** @param list<array{id: int, label: string}> $options */
    private function optionLabel(array $options, string $value): ?string
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        foreach ($options as $option) {
            if ($option['id'] === $id) {
                return $option['label'];
            }
        }

        return null;
    }
}
