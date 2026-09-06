<?php

namespace App\Support;

use App\Models\FormSubmission;

/**
 * BR-SM-17 — gerbang langkah wizard.
 *
 * Navigasi **bebas maju-mundur**. Yang menggerbang adalah prasyarat data per
 * langkah, bukan urutannya. Mengunci berurutan menyiksa tanpa alasan:
 * memperbaiki judul di langkah 2 saat berada di langkah 6 tidak boleh
 * menuntut mundur empat layar.
 *
 * Satu-satunya prasyarat nyata sejauh ini: langkah Anggaran butuh
 * `scheme_id`, karena batasnya berasal dari skema (BR-BUD-01). Form yang
 * tidak punya `scheme_selector` tidak pernah menuntutnya.
 *
 * Langkah Review (7) sengaja **tidak** bergerbang — yang bergerbang tombol
 * Submit-nya, dijaga `RequiredFieldGuard`.
 *
 * `final class` statis mengikuti preseden `RequiredFieldGuard`.
 */
final class WizardStepGate
{
    /** Tujuh langkah ADR 0012. */
    public const STEP_FORM_PERIOD = 1;

    public const STEP_BASIC_INFO = 2;

    public const STEP_SCHEME = 3;

    public const STEP_MEMBERS = 4;

    public const STEP_BUDGET = 5;

    public const STEP_FILES = 6;

    public const STEP_REVIEW = 7;

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
