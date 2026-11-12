<?php

namespace App\Actions\FormSubmission;

use App\Actions\Form\PinPhaseVersion;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Queries\Period\CohortReadiness;
use App\Support\AccountStateGuard;
use App\Support\Audit\AuditTrail;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class StartSubmission
{
    use AsAction;

    /**
     * Langkah 1 wizard (ADR 0012). Idempoten: frontend memanggilnya setiap kali
     * peneliti membuka sebuah Form, jadi ia harus memakai ulang draft yang sudah
     * ada alih-alih melahirkan kembar.
     */
    public function handle(
        int $formId,
        int $submissionPeriodId,
        FormPhaseDetail $formPhaseDetail,
        User $user
    ): FormSubmission {
        // BR-FE-01 — periode yang sudah berakhir atau ditutup paksa Operator
        // tidak melahirkan pengajuan baru. Diperiksa lebih dulu supaya draft
        // yang sudah ada pun tidak dibangkitkan kembali di periode yang mati.
        SubmissionPeriodGate::assertOpen(SubmissionPeriod::find($submissionPeriodId));

        // BR-IAM-03 — kelengkapan profil menggerbang **pembuatan** pengajuan,
        // sumbu yang berbeda dari verifikasi NIDN. Akun terverifikasi dengan
        // profil kosong tetap tertahan di sini.
        AccountStateGuard::assertProfileComplete($user);

        // `::make()->handle()`, bukan `::run()`: run() milik AsObject dideklarasikan `mixed`
        // (tanpa stub PHPStan), jadi PHPStan level 9 tak bisa menyempitkannya ke ?FormSubmission.
        // make() memberi instance bertipe static dengan tipe kembalian yang benar.
        $existing = ResolveWizardSubmission::make()->handle($formId, $submissionPeriodId, $user);

        // BR-SM-05 — baris yang tidak lagi boleh disunting (terminal atau sedang direview)
        // tidak dipakai ulang, jadi status terminal melahirkan baris baru alih-alih membentur
        // baris lama. Kelayakan suntingnya tetap tertulis satu kali, di FormSubmissionPolicy.
        if ($existing !== null && $user->can('update', $existing)) {
            return $existing;
        }

        $this->assertAcceptsNewSubmissions($formId, $submissionPeriodId, $formPhaseDetail);

        return $this->createDraft($formId, $submissionPeriodId, $formPhaseDetail, $user);
    }

    private function createDraft(int $formId, int $submissionPeriodId, FormPhaseDetail $formPhaseDetail, User $user): FormSubmission
    {
        return DB::transaction(function () use ($formId, $submissionPeriodId, $formPhaseDetail, $user): FormSubmission {
            // BR-FE-32 — pengajuan pertama angkatan memaku versi strukturnya; penolakan langkah
            // versi lama menggulung pakuannya kembali.
            PinPhaseVersion::run(SubmissionPeriod::query()->findOrFail($submissionPeriodId), $formPhaseDetail);

            // ADR 0012 — created_at baris ini menjadi jangkar Temporal Field Binding
            // (BR-FE-11), jadi ia harus lahir saat peneliti mulai mengisi.
            $tercatat = FormSubmission::create([
                'form_id' => $formId,
                'submitted_by' => $user->id,
                'submission_period_id' => $submissionPeriodId,
                'form_phase_detail_id' => $formPhaseDetail->id,
                'is_submitted' => false,
                'last_completed_step' => 1,
            ]);

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record('Draft pengajuan dibuat', $tercatat, $user);

            return $tercatat;
        });
    }

    // BR-FE-19 / FE-DELETE-05 — Form nonaktif tidak menerima pengajuan baru; draft yang masih
    // boleh disunting sudah dikembalikan lebih dulu, jadi tidak ikut tertolak (W77).
    // BR-FE-20 / FE-DELETE-15 — tahap nonaktif menutup pengajuan baru dengan cara yang sama.
    private function assertAcceptsNewSubmissions(int $formId, int $submissionPeriodId, FormPhaseDetail $detail): void
    {
        if (Form::query()->whereKey($formId)->where('is_active', false)->exists()) {
            throw ValidationException::withMessages(['form_id' => 'Form ini sudah tidak menerima pengajuan baru.']);
        }

        if (FormPhase::query()->whereKey($detail->form_phase_id)->where('is_active', false)->exists()) {
            throw ValidationException::withMessages([
                'form_phase_id' => 'Tahap ini sudah tidak menerima pengajuan baru.',
            ]);
        }

        // BR-MON-01, W139 — laporan monev dan Upload Luaran lahir dari pengajuan yang sudah disetujui.
        if (!$detail->opensSubmission()) {
            throw ValidationException::withMessages([
                'form_phase_detail_id' => 'Tahap ini tidak menerima pengajuan baru. Laporan monev dan luaran diisi dari pengajuan yang sudah disetujui.',
            ]);
        }

        // R1-W2, BR-FE-26 (ADR 0019) — tanggalnya milik periode, jadi syaratnya tahap ditautkan
        // periode itu (FE-WINDOW-13), bukan "tenggat langkah milik periode ini".
        $linked = SubmissionPeriodPhase::query()
            ->where('submission_period_id', $submissionPeriodId)
            ->where('form_phase_id', $detail->form_phase_id)
            ->exists();

        if (!$linked) {
            throw ValidationException::withMessages(['form_phase_id' => 'Tahap ini tidak dibuka di periode ini.']);
        }

        // BR-FE-36 — angkatan belum berjalan dengan isu memblokir menolak pengajuan baru.
        $readiness = CohortReadiness::run([$submissionPeriodId])[CohortReadiness::key($submissionPeriodId, $detail->form_phase_id)] ?? null;

        if ($readiness?->blocksNewSubmissions() === true) {
            throw ValidationException::withMessages([
                'form_phase_id' => 'Tahap ini belum siap menerima pengajuan di periode ini. Hubungi Operator LPPM.',
            ]);
        }
    }
}
