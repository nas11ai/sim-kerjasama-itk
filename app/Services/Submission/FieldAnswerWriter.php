<?php

namespace App\Services\Submission;

use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Support\PrivateUploadDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Jawaban peneliti ke sebuah pengajuan. Dipanggil dari dalam transaksi SaveSubmissionDraft dan
 * SaveBiodata, yang menulis jejak auditnya.
 */
final class FieldAnswerWriter
{
    /**
     * Seluruh jawaban digantikan: field yang dikosongkan ikut terhapus. Untuk layar yang memuat ulang
     * jawaban tersimpan sebelum disunting.
     *
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas, menimpa jawaban field itu
     *
     * @throws ValidationException
     */
    public static function replace(FormSubmission $submission, array $answers, array $uploads, string $directory): void
    {
        $values = self::storedValues($submission, $answers, $uploads, $directory);

        $submission->formFieldResponses()->delete();

        self::create($submission, $values);
    }

    /**
     * [SM-WIZARD-02] Hanya field yang terisi yang ditimpa; jawaban kosong membiarkan jawaban tersimpan.
     * Layar wizard tidak memuat ulang jawaban, jadi kosong di sana berarti belum disentuh — dulu setiap
     * simpan setelah halaman dibuka ulang menghapus isian sebelumnya. Akibatnya jawaban tak bisa dikosongkan.
     *
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas, menimpa jawaban field itu
     *
     * @throws ValidationException
     */
    public static function fill(FormSubmission $submission, array $answers, array $uploads, string $directory): void
    {
        $values = array_filter(self::storedValues($submission, $answers, $uploads, $directory), fn (?string $value): bool => $value !== null);

        $submission->formFieldResponses()->whereIn('form_field_id', array_keys($values))->delete();

        self::create($submission, $values);
    }

    /**
     * Hanya field yang dikirim yang diganti, dan jawaban kosong menghapusnya. Untuk wizard v2, yang
     * memuat ulang jawaban tersimpan: kosong di sana berarti dikosongkan, sementara field berkas yang
     * tidak diunggah ulang memang tidak dikirim dan karenanya tetap.
     *
     * @param  array<int, ?string>  $answers  id field => nilai; null berarti kosong
     * @param  array<int, UploadedFile>  $uploads  id field => berkas, menimpa jawaban field itu
     *
     * @throws ValidationException
     */
    public static function overwrite(FormSubmission $submission, array $answers, array $uploads, string $directory): void
    {
        $values = self::storedValues($submission, $answers, $uploads, $directory);

        $submission->formFieldResponses()->whereIn('form_field_id', array_keys($values))->delete();

        self::create($submission, $values);
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
            'responses' => 'Jawaban memuat field yang bukan milik formulir ini.',
        ]);
    }
}
