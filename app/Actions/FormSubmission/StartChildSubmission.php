<?php

namespace App\Actions\FormSubmission;

use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use App\Support\MonevAccessGate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-04 — child FormSubmission (laporan monev, kelengkapan berkas) hanya
 * lahir dari induk yang **sudah APPROVED**. Gerbangnya status induk, bukan
 * keberadaan fasenya: fase monev boleh sudah terpasang di Form sejak awal.
 *
 * @method static FormSubmission run(FormSubmission $parent, FormPhaseDetail $detail, User $user)
 */
class StartChildSubmission
{
    use AsAction;

    /**
     * Laporan tak berdasar sebelum kegiatan disetujui. Child tak ikut mati
     * bersama induk: penarikan induk (WITHDRAWN) membekukan kegiatan, tapi
     * laporan terkirim tetap data historis; hapus = hilang bukti pelaporan.
     */
    public function handle(FormSubmission $parent, FormPhaseDetail $detail, User $user): FormSubmission
    {
        // BR-FE-04, BR-MON-01/02/08 — tiga syarat sekaligus: induk disetujui,
        // belum ditarik, tahap sebelumnya tuntas. Satu tempat supaya jalur
        // HTTP dan lainnya tak pernah menegakkan separuhnya saja.
        MonevAccessGate::assertCycleOpen($parent, $detail);

        // BR-MON-03 — satu child per (induk, fase). Index unik parsial menjaga
        // lomba-balap; pencarian ini yang membuat pemanggilan kedua idempoten
        // alih-alih menabrak constraint.
        $existing = FormSubmission::query()
            ->where('parent_submission_id', $parent->id)
            ->where('form_phase_detail_id', $detail->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $accessControl = $this->accessControlOf($detail);

        $tercatat = FormSubmission::create([
            'form_id' => $accessControl->form_id,
            'submitted_by' => $user->id,
            'submission_period_id' => $parent->submission_period_id,
            'form_phase_detail_id' => $detail->id,
            'parent_submission_id' => $parent->id,
            'is_submitted' => false,
            'last_completed_step' => 1,
        ]);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Laporan tahap dimulai', $tercatat, $user);

        return $tercatat;
    }

    /**
     * @throws ValidationException
     */
    private function accessControlOf(FormPhaseDetail $detail): FormAccessControl
    {
        $accessControl = $detail->formAccessControl;

        if ($accessControl === null) {
            throw ValidationException::withMessages([
                'form_phase_detail_id' => 'Fase ini belum tertaut ke formulir mana pun.',
            ]);
        }

        return $accessControl;
    }
}
