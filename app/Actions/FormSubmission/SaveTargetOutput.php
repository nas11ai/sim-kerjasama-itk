<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\SubmissionTargetOutput;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-14 dan BR-SM-15 — menambah atau mengubah target luaran.
 *
 * Tipe disaring **dua tingkat**, dan urutannya penting:
 *
 *   1. `output_type_definitions` — taksonomi; berlaku selalu.
 *   2. `scheme.rules.allowed_output_types` — mempersempit lagi, hanya kalau
 *      submission punya skema **dan** skema itu menyatakan daftarnya. Skema
 *      tanpa key itu tidak membatasi apa pun (BR-SM-14).
 *
 * Pengajuan tanpa skema karena itu hanya kena penyaring pertama.
 */
class SaveTargetOutput
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(
        FormSubmission $submission,
        User $user,
        string $outputType,
        ?SubmissionTargetOutput $target = null
    ): SubmissionTargetOutput {
        if (!$user->can('manageTargetOutput', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat mengubah target luaran, dan hanya selama pengajuan belum ditolak atau ditarik.'
            );
        }

        $this->guardType($submission, $outputType);

        if ($target !== null) {
            $target->update(['output_type' => $outputType]);

            return $target;
        }

        $tercatat = SubmissionTargetOutput::create([
            'form_submission_id' => $submission->id,
            'output_type' => $outputType,
        ]);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Target luaran disimpan', $tercatat, $user);

        return $tercatat;
    }

    /**
     * @throws ValidationException
     */
    private function guardType(FormSubmission $submission, string $outputType): void
    {
        if (!in_array($outputType, OutputTypeDefinition::activeCodes(), true)) {
            throw ValidationException::withMessages([
                'output_type' => "Tipe luaran \"{$outputType}\" tidak terdaftar.",
            ]);
        }

        $allowed = $this->schemeAllowList($submission);

        // null berarti skema tidak membatasi — berbeda dari daftar kosong,
        // yang berarti tidak ada tipe yang diizinkan sama sekali.
        if ($allowed === null || in_array($outputType, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'output_type' => "Tipe luaran \"{$outputType}\" tidak diizinkan skema pengajuan ini.",
        ]);
    }

    /** @return list<string>|null */
    private function schemeAllowList(FormSubmission $submission): ?array
    {
        $rules = $submission->scheme?->rules;

        if (!is_array($rules) || !isset($rules['allowed_output_types'])) {
            return null;
        }

        $allowed = $rules['allowed_output_types'];

        if (!is_array($allowed)) {
            return null;
        }

        return array_values(array_filter($allowed, 'is_string'));
    }
}
