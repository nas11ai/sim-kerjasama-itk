<?php

namespace App\Actions\Override;

use App\Data\Request\PhaseOverrideInput;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\User;
use App\Notifications\PhaseOverrideGrantedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-09, BR-FE-10 — Operator memperpanjang akses satu pengajuan pada satu
 * fase. Override ialah pengecualian, jadi paling perlu dipertanggungjawabkan:
 * alasan wajib, semua pemberiannya masuk jejak audit lewat LogsActivity model.
 *
 * @method static FormSubmissionOverride run(FormSubmission $submission, FormPhaseDetail $detail, User $grantedBy, PhaseOverrideInput $input)
 */
class GrantPhaseOverride
{
    use AsAction;

    public function handle(
        FormSubmission $submission,
        FormPhaseDetail $detail,
        User $grantedBy,
        PhaseOverrideInput $input,
    ): FormSubmissionOverride {
        $expiry = $this->expiry($input);

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
            'reason' => trim($input->reason),
            'expires_at' => $expiry,
            'is_active' => true,
        ]);

        // BR-FE-16 — pemberitahuannya menyebut sampai kapan, tapi tidak
        // menyebut alasannya: alasan hanya untuk Operator dan Admin.
        Deliver::to($submission->submittedBy, new PhaseOverrideGrantedNotification($submission, $detail, $expiry));

        $tercatat = $override;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Perpanjangan akses fase diberikan', $tercatat, $grantedBy);

        return $tercatat;
    }

    /**
     * @throws ValidationException
     */
    private function expiry(PhaseOverrideInput $input): ?Carbon
    {
        // BR-FE-09 — alasan wajib. Ditegakkan di Action, bukan hanya di
        // FormRequest: override juga bisa diberikan dari jalur lain, dan
        // pengecualian tanpa alasan tidak bisa diaudit.
        if (trim($input->reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan perpanjangan wajib diisi.',
            ]);
        }

        $expiry = $input->expiresAt === null ? null : Carbon::parse($input->expiresAt);

        if ($expiry !== null && $expiry->isPast()) {
            throw ValidationException::withMessages([
                'expires_at' => 'Masa berlaku perpanjangan harus di masa depan.',
            ]);
        }

        return $expiry;
    }
}
