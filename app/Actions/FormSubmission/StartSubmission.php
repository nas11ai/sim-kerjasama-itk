<?php

namespace App\Actions\FormSubmission;

use App\Models\Form;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\AccountStateGuard;
use App\Support\Audit\AuditTrail;
use App\Support\SubmissionPeriodGate;
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

        $this->assertAcceptsNewSubmissions($formId);

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
    }

    // BR-FE-19 / FE-DELETE-05 — Form nonaktif tidak menerima pengajuan baru; draft yang
    // masih boleh disunting sudah dikembalikan lebih dulu, jadi tidak ikut tertolak (W77).
    private function assertAcceptsNewSubmissions(int $formId): void
    {
        if (!Form::query()->whereKey($formId)->where('is_active', false)->exists()) {
            return;
        }

        throw ValidationException::withMessages(['form_id' => 'Form ini sudah tidak menerima pengajuan baru.']);
    }
}
