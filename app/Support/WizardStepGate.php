<?php

namespace App\Support;

use App\Models\FormSubmission;

/**
 * BR-SM-17 — gerbang langkah wizard. Navigasi **bebas maju-mundur**; yang
 * menggerbang prasyarat data per langkah, bukan urutannya — memperbaiki judul
 * di langkah 2 dari langkah 6 tidak boleh menuntut mundur empat layar.
 */
final class WizardStepGate
{
    /** Tujuh langkah ADR 0012; kelasnya statis seperti `RequiredFieldGuard`. */
    public const STEP_FORM_PERIOD = 1;

    public const STEP_BASIC_INFO = 2;

    public const STEP_SCHEME = 3;

    public const STEP_MEMBERS = 4;

    public const STEP_BUDGET = 5;

    public const STEP_FILES = 6;

    // Langkah Review sengaja **tidak** bergerbang — yang bergerbang tombol
    // Submit-nya, dijaga `RequiredFieldGuard`.
    public const STEP_REVIEW = 7;

    public const LABELS = [
        self::STEP_FORM_PERIOD => 'Pilih Form & Periode',
        self::STEP_BASIC_INFO => 'Informasi Dasar',
        self::STEP_SCHEME => 'Skema, TRL & Target Luaran',
        self::STEP_MEMBERS => 'Anggota Penelitian',
        self::STEP_BUDGET => 'Rencana Anggaran',
        self::STEP_FILES => 'Upload Berkas',
        self::STEP_REVIEW => 'Review & Submit',
    ];

    /**
     * Langkah tempat sebuah field Form Engine diisi. Form Engine tidak menyimpan
     * langkah per field, jadi tipe field-lah yang menentukannya (fe-v2-design W13).
     */
    public static function stepForFieldType(string $type): int
    {
        return match ($type) {
            'scheme_selector', 'trl_selector' => self::STEP_SCHEME,
            'file' => self::STEP_FILES,
            default => self::STEP_BASIC_INFO,
        };
    }

    public static function isOpen(FormSubmission $submission, int $step): bool
    {
        return self::blockedReason($submission, $step) === null;
    }

    /**
     * Alasan langkah terkunci, atau null kalau terbuka.
     *
     * Mengembalikan alasannya — bukan sekadar boolean — karena peneliti yang
     * hanya melihat langkah terkunci tidak tahu harus berbuat apa.
     */
    public static function blockedReason(FormSubmission $submission, int $step): ?string
    {
        // Satu-satunya prasyarat nyata sejauh ini: batas anggaran berasal dari
        // skema (BR-BUD-01), jadi langkah Anggaran butuh `scheme_id`.
        if ($step !== self::STEP_BUDGET) {
            return null;
        }

        if (!self::requiresScheme($submission)) {
            return null;
        }

        if ($submission->scheme_id !== null) {
            return null;
        }

        return 'Pilih skema di langkah 3 lebih dulu — batas anggaran berasal dari skema.';
    }

    /** Langkah tempat draft dilanjutkan. */
    public static function resumeStep(FormSubmission $submission): int
    {
        $last = $submission->last_completed_step;

        return $last >= self::STEP_FORM_PERIOD ? $last : self::STEP_FORM_PERIOD;
    }

    /**
     * Form yang tidak punya field `scheme_selector` tidak pernah menuntut
     * skema — batas anggarannya memang tidak berlaku (BR-SCH-04).
     */
    private static function requiresScheme(FormSubmission $submission): bool
    {
        $form = $submission->form;

        return $form->formFields()
            ->whereHas('fieldType', fn ($q) => $q->where('name', 'scheme_selector'))
            ->exists();
    }
}
