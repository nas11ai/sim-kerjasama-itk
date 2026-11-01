<?php

namespace App\Support\Review;

use App\Models\FormSubmission;

/**
 * BR-REV-01 — jumlah reviewer aktif yang memindahkan pengajuan ke UNDER_REVIEW. Dipakai
 * AssignReviewer (transisi) dan layar Operator (indikator), supaya keduanya sepakat.
 */
final class MinimumReviewers
{
    /**
     * Ambang bawaan kalau skema tidak menyatakannya atau submission tak
     * punya skema. Bukan pengganti `min_reviewer_count` (BR-REV-01:
     * ambangnya milik skema); hanya berlaku bila skema tak bisa ditanya.
     */
    public const DEFAULT = 2;

    /**
     * Ambang dari `scheme.rules.min_reviewer_count`. Nilainya berasal dari
     * kolom JSONB, jadi bentuknya diperiksa — bukan di-cast — supaya angka
     * yang salah bentuk tidak diam-diam berubah jadi ambang yang keliru.
     */
    public static function for(FormSubmission $submission): int
    {
        $rules = $submission->scheme?->rules;

        if (!is_array($rules)) {
            return self::DEFAULT;
        }

        $configured = $rules['min_reviewer_count'] ?? null;

        return is_int($configured) && $configured > 0 ? $configured : self::DEFAULT;
    }
}
