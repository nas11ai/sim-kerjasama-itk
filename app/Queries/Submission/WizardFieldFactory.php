<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Wizard\WizardFieldData;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;

/**
 * Satu field Form Engine beserta jawaban tersimpannya, sebagaimana diisi peneliti. Dipakai
 * wizard pengajuan dan laporan monev supaya keduanya merender isian yang sama.
 */
final class WizardFieldFactory implements ChangesNoState
{
    public static function of(FormSubmission $submission, FormField $field, ?FormFieldResponse $response): WizardFieldData
    {
        $type = $field->fieldType->name ?? '';
        $value = $response?->value;
        $isFile = $type === 'file';
        $hasFile = $isFile && $value !== null && $value !== '';

        return new WizardFieldData(
            id: $field->id,
            label: $field->label,
            type: $type,
            // BR-FE-11 — kewajiban dihitung terhadap created_at pengajuan ini.
            required: $field->isRequiredFor($submission),
            // Path penyimpanan berkas tidak dikirim ke browser; yang tampil nama dan tautannya.
            value: $isFile ? null : $value,
            options: WizardFieldOptions::run($submission, $field, $type),
            fileName: $hasFile ? basename($value) : null,
            downloadUrl: $hasFile ? route('user.submissions.files.show', [$submission->id, $response->id]) : null,
        );
    }
}
