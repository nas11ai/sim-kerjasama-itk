<?php

namespace App\Support;

use App\Models\FieldType;
use Illuminate\Validation\ValidationException;

/**
 * BR-SM-22 — `submission_title` adalah field teks berperan khusus: jawabannya menjadi judul
 * pengajuan (`form_submissions.title`). Dua field seperti itu di satu Form membuat judulnya
 * bergantung pada urutan simpan, jadi Form paling banyak punya satu.
 */
final class SubmissionTitleField
{
    public const TYPE = 'submission_title';

    /**
     * @param  array<int, int|string>  $fieldTypeIds  seluruh field type yang akan dimiliki Form
     *
     * @throws ValidationException
     */
    public static function assertAtMostOne(array $fieldTypeIds): void
    {
        if ($fieldTypeIds === []) {
            return;
        }

        $titleTypeId = FieldType::query()->where('name', self::TYPE)->value('id');

        if ($titleTypeId === null) {
            return;
        }

        $count = count(array_filter($fieldTypeIds, static fn ($id): bool => (int) $id === $titleTypeId));

        if ($count <= 1) {
            return;
        }

        throw ValidationException::withMessages([
            'fields' => 'Form hanya boleh punya satu field bertipe Judul pengajuan.',
        ]);
    }
}
