<?php

namespace App\Actions\Override;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\User;
use App\Notifications\PhaseOverrideGrantedNotification;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-09, BR-FE-10 — Operator memberi perpanjangan akses untuk satu
 * pengajuan pada satu fase.
 *
 * Override adalah pengecualian terhadap aturan, jadi ia yang paling perlu
 * dipertanggungjawabkan: alasannya wajib, dan seluruh pemberiannya masuk jejak
 * audit lewat LogsActivity di modelnya.
 *
 * @method static FormSubmissionOverride run(FormSubmission $submission, FormPhaseDetail $detail, User $grantedBy, string $reason, ?string $expiresAt = null)
 */
class GrantPhaseOverride
{
    use AsAction;

    public function handle(
        FormSubmission $submission,
        FormPhaseDetail $detail,
        User $grantedBy,
        string $reason,
        ?string $expiresAt = null
    ): FormSubmissionOverride {
        // BR-FE-09 — alasan wajib. Ditegakkan di Action, bukan hanya di
        // FormRequest: override juga bisa diberikan dari jalur lain, dan
        // pengecualian tanpa alasan tidak bisa diaudit.
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan perpanjangan wajib diisi.',
            ]);
        }

        $expiry = $expiresAt === null ? null : Carbon::parse($expiresAt);

        if ($expiry !== null && $expiry->isPast()) {
            throw ValidationException::withMessages([
                'expires_at' => 'Masa berlaku perpanjangan harus di masa depan.',
            ]);
        }

        // Satu pengajuan + satu fase hanya perlu satu izin berjalan. Memberi
        // yang baru mencabut yang lama supaya tidak ada dua masa berlaku yang
        // saling bertengkar.
        FormSubmissionOverride::query()
            ->where('form_submission_id', $submission->id)
            ->where('form_phase_detail_id', $detail->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $override = FormSubmissionOverride::create([
            'form_submission_id' => $submission->id,
            'form_phase_detail_id' => $detail->id,
            'granted_by' => $grantedBy->id,
            'reason' => trim($reason),
            'expires_at' => $expiry,
            'is_active' => true,
        ]);

        // BR-FE-16 — pemberitahuannya menyebut sampai kapan, tapi tidak
        // menyebut alasannya: alasan hanya untuk Operator dan Admin.
        $submission->submittedBy->notify(new PhaseOverrideGrantedNotification($submission, $detail, $expiry));

        $tercatat = $override;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Perpanjangan akses fase diberikan', $tercatat, $grantedBy);

        return $tercatat;
    }
}
