<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\SubmissionTargetOutput;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-14 dan BR-SM-15 — menambah atau mengubah target luaran.
 *
 * Tipe disaring **dua tingkat** di `guardType()`, dan urutannya penting.
 */
class SaveTargetOutput
{
    use AsAction;

    public const AUDIT_CREATED = 'Target luaran disimpan';

    public const AUDIT_UPDATED = 'Target luaran diubah';

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
            return $this->change($submission, $target, $user, $outputType);
        }

        $tercatat = SubmissionTargetOutput::create([
            'form_submission_id' => $submission->id,
            'output_type' => $outputType,
        ]);

        // BR-SM-15, BR-RPT-05 — target adalah bagian aggregate pengajuan; jejaknya ikut terbaca tim lewat AuditScope.
        AuditTrail::record(self::AUDIT_CREATED, $submission, $user, new AuditChange(new: ['output_type' => $outputType]));

        return $tercatat;
    }

    private function change(FormSubmission $submission, SubmissionTargetOutput $target, User $user, string $outputType): SubmissionTargetOutput
    {
        $old = $target->output_type;
        $target->update(['output_type' => $outputType]);

        // BR-SM-15, BR-RPT-09 — perubahan janji, termasuk pasca-APPROVED, tercatat dengan nilai lama dan baru.
        AuditTrail::record(
            self::AUDIT_UPDATED,
            $submission,
            $user,
            new AuditChange(old: ['output_type' => $old], new: ['output_type' => $outputType]),
        );

        return $target;
    }

    /**
     * @throws ValidationException
     */
    private function guardType(FormSubmission $submission, string $outputType): void
    {
        // Tingkat 1: taksonomi `output_type_definitions`, berlaku selalu.
        if (!in_array($outputType, OutputTypeDefinition::activeCodes(), true)) {
            throw ValidationException::withMessages([
                'output_type' => "Tipe luaran \"{$outputType}\" tidak terdaftar.",
            ]);
        }

        // Tingkat 2: `scheme.rules.allowed_output_types` mempersempit lagi. Skema
        // tanpa key itu tidak membatasi (BR-SM-14) — tanpa skema hanya kena tingkat 1.
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
