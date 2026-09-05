<?php

namespace App\Support;

use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Validation\ValidationException;

/**
 * BR-SM-02 dan BR-FE-11 — field wajib harus terisi sebelum submit, tapi hanya
 * field yang sudah wajib **sebelum** submission dibuat.
 *
 * `final class` statis mengikuti preseden `OptimisticLock`: tidak ada state
 * instance yang dipegang, dan sebuah guard bukan use case sehingga ia tidak
 * berhak jadi Action.
 */
final class RequiredFieldGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertComplete(FormSubmission $submission): void
    {
        // form_id bukan nullable dan Form tidak memakai SoftDeletes, jadi
        // relasi ini selalu ada — PHPStan menolak pengecekan null di sini
        // karena memang tidak pernah tercapai (identical.alwaysFalse).
        $form = $submission->form;

        // FormField memakai SoftDeletes, jadi relasi ini sudah menyaring field
        // yang dihapus — itulah yang memenuhi [FE-TEMPORAL-08]. Menambahkan
        // withTrashed() di sini akan mematahkannya.
        $required = $form->formFields()->get()->filter(
            fn (FormField $field): bool => $field->isRequiredFor($submission)
        );

        if ($required->isEmpty()) {
            return;
        }

        $filled = $submission->formFieldResponses()
            ->whereIn('form_field_id', $required->pluck('id'))
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('form_field_id')
            ->all();

        $missing = $required->reject(
            fn (FormField $field): bool => in_array($field->id, $filled, true)
        );

        if ($missing->isEmpty()) {
            return;
        }

        // Error per field supaya wizard bisa menyorot baris yang salah —
        // dituntut [SM-SUBMIT-07] dan [SM-WIZARD-06].
        throw ValidationException::withMessages(
            $missing->mapWithKeys(fn (FormField $field): array => [
                'field_'.$field->id => "Field '{$field->label}' wajib diisi sebelum submit.",
            ])->all()
        );
    }
}
