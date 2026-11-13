<?php

namespace App\Services\Submission;

use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Support\PrivateUploadDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Jawaban peneliti ke sebuah pengajuan. Dipanggil dari dalam transaksi Action pemanggilnya
 * (mis. SaveSubmissionDraft), yang menulis jejak auditnya.
 */
final class FieldAnswerWriter
{
    /**
     * Hanya field yang dikirim yang diganti, dan jawaban kosong menghapusnya. Untuk wizard v2, yang
     * memuat ulang jawaban tersimpan: kosong di sana berarti dikosongkan, sementara field berkas yang
     * tidak diunggah ulang memang tidak dikirim dan karenanya tetap.
     *
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas, menimpa jawaban field itu
     * @return bool ada jawaban tersimpan yang berubah (W161: hanya itu yang menggeser cap kunci)
     *
     * @throws ValidationException
     */
    public static function overwrite(FormSubmission $submission, array $answers, array $uploads, string $directory): bool
    {
        $values = self::storedValues($submission, $answers, $uploads, $directory);
        $before = $submission->formFieldResponses()->whereIn('form_field_id', array_keys($values))->pluck('value', 'form_field_id')->all();

        $submission->formFieldResponses()->whereIn('form_field_id', array_keys($values))->delete();

        self::create($submission, $values);

        return $uploads !== [] || self::differs($before, $values);
    }

    /**
     * @param  array<array-key, mixed>  $before  id field => nilai tersimpan
     * @param  array<int, ?string>  $values  id field => nilai baru; null berarti tanpa baris
     */
    private static function differs(array $before, array $values): bool
    {
        foreach ($values as $fieldId => $value) {
            if (($before[$fieldId] ?? null) !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Berkas disimpan setelah field-nya terbukti milik form ini, supaya kiriman yang ditolak tidak
     * meninggalkan berkas yatim.
     *
     * @param  array<int, ?string>  $answers
     * @param  array<int, UploadedFile>  $uploads
     * @return array<int, ?string>
     *
     * @throws ValidationException
     */
    private static function storedValues(FormSubmission $submission, array $answers, array $uploads, string $directory): array
    {
        self::assertOwnFields($submission, array_keys($answers + $uploads));

        foreach ($uploads as $fieldId => $file) {
            $answers[$fieldId] = $file->store($directory, PrivateUploadDisk::NAME) ?: null;
        }

        return $answers;
    }

    /** @param  array<int, ?string>  $values */
    private static function create(FormSubmission $submission, array $values): void
    {
        foreach ($values as $fieldId => $value) {
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
            'responses' => 'Jawaban memuat isian yang bukan milik formulir ini.',
        ]);
    }
}
