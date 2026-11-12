<?php

namespace App\Actions\Review;

use App\Models\EvaluationFormPin;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-18 — memaku versi `$form` untuk angkatan × langkah pengajuan ini saat draf penilaian
 * pertama di sana tersimpan; pemanggil berikutnya mendapat versi pakuan yang sama.
 *
 * @method static ReviewEvaluationFormVersion run(FormSubmission $submission, ReviewEvaluationForm $form)
 */
class PinEvaluationVersion
{
    use AsAction;

    public function handle(FormSubmission $submission, ReviewEvaluationForm $form): ReviewEvaluationFormVersion
    {
        $latest = $form->latestVersion()->firstOrFail();

        // Pengajuan tanpa periode atau langkah bukan anggota angkatan mana pun: tidak ada yang dipaku.
        if ($submission->submission_period_id === null || $submission->form_phase_detail_id === null) {
            return $latest;
        }

        // Dua draf pertama yang hampir bersamaan bertemu di indeks unik angkatan; createOrFirst
        // memakai savepoint, jadi yang kalah membaca baris pemenang tanpa membatalkan transaksinya.
        $pin = EvaluationFormPin::query()->createOrFirst(
            [
                'submission_period_id' => $submission->submission_period_id,
                'form_phase_detail_id' => $submission->form_phase_detail_id,
                'review_evaluation_form_id' => $form->id,
            ],
            ['review_evaluation_form_version_id' => $latest->id, 'pinned_at' => now()],
        );

        if ($pin->wasRecentlyCreated) {
            AuditTrail::record('Versi form evaluasi dipaku', $pin, change: new AuditChange(new: [
                'review_evaluation_form_id' => $form->id,
                'version' => $latest->number,
            ]));
        }

        return $pin->reviewEvaluationFormVersion;
    }
}
