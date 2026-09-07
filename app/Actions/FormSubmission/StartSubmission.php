<?php

namespace App\Actions\FormSubmission;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\SubmissionPeriodGate;
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

        // ::make()->handle() dan bukan ::run(): run() milik AsObject dideklarasikan
        // `mixed` (paket tidak menyediakan stub PHPStan), jadi PHPStan level 9 tidak
        // bisa menyempitkannya ke ?FormSubmission. make() memberi instance bertipe
        // static, dan handle()-nya sendiri sudah membawa tipe kembalian yang benar.
        $existing = ResolveWizardSubmission::make()->handle($formId, $submissionPeriodId, $user);

        // BR-SM-05 lewat Policy — baris yang tidak lagi boleh disunting (terminal,
        // atau sedang direview) tidak dipakai ulang. Itulah yang membuat status
        // terminal melahirkan baris baru alih-alih membentur baris lama; kelayakan
        // suntingnya tetap tertulis satu kali saja, di FormSubmissionPolicy.
        if ($existing !== null && $user->can('update', $existing)) {
            return $existing;
        }

        // ADR 0012 — created_at baris ini menjadi jangkar Temporal Field Binding
        // (BR-FE-11), jadi ia harus lahir saat peneliti mulai mengisi.
        return FormSubmission::create([
            'form_id' => $formId,
            'submitted_by' => $user->id,
            'submission_period_id' => $submissionPeriodId,
            'form_phase_detail_id' => $formPhaseDetail->id,
            'is_submitted' => false,
            'last_completed_step' => 1,
        ]);
    }
}
