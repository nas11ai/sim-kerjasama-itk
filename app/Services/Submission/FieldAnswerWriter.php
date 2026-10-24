<?php

namespace App\Services\Submission;

use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Jawaban peneliti menggantikan seluruh jawaban sebuah pengajuan. Dipanggil dari dalam transaksi
 * SaveSubmissionDraft dan SaveBiodata, yang menulis jejak auditnya.
 */
final class FieldAnswerWriter
{
    /**
     * Berkas disimpan setelah field-nya terbukti milik form ini, supaya kiriman yang ditolak
     * tidak meninggalkan berkas yatim.
     *
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas, menimpa jawaban field itu
     *
     * @throws ValidationException
     */
    public static function replace(FormSubmission $submission, array $answers, array $uploads, string $directory): void
    {
        self::assertOwnFields($submission, array_keys($answers + $uploads));

        foreach ($uploads as $fieldId => $file) {
            $answers[$fieldId] = $file->store($directory, 'public') ?: null;
        }

        $submission->formFieldResponses()->delete();

        foreach ($answers as $fieldId => $value) {
            if ($value === null) {
                continue;
            }

            FormFieldResponse::create([
                'form_submission_id' => $submission->id,
                'form_field_id' => $fieldId,
                'value' => $value,
            ]);
        }
    }

    /**
     * Validatornya hanya memeriksa field itu ada di tabel, jadi jawaban untuk field form lain
     * dulu tersimpan diam-diam di pengajuan ini.
     *
     * @param  list<int>  $fieldIds
     *
     * @throws ValidationException
     */
    private static function assertOwnFields(FormSubmission $submission, array $fieldIds): void
    {
        $owned = FormField::query()
            ->where('form_id', $submission->form_id)
            ->whereIn('id', $fieldIds)
            ->count();

        if ($owned === count($fieldIds)) {
            return;
        }

        throw ValidationException::withMessages([
            'responses' => 'Jawaban memuat field yang bukan milik formulir ini.',
        ]);
    }
}
