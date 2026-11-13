<?php

namespace App\Actions\FormSubmission;

use App\Actions\Form\AssertFormAccessible;
use App\Actions\Scheme\SyncSchemeSelection;
use App\Actions\Scheme\SyncTrlSelection;
use App\Data\Request\SubmissionAnswersInput;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Submission\FieldAnswerWriter;
use App\Support\Audit\AuditTrail;
use App\Support\OptimisticLock;
use App\Support\PhaseAccessGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Menyimpan jawaban satu langkah wizard ke baris yang dilahirkan langkah 1 (ADR 0012), tanpa
 * validasi kelengkapan [FE-TEMPORAL-09]. Dipanggil SaveWizardStep untuk langkah yang membawa jawaban.
 *
 * @method static FormSubmission run(SubmissionAnswersInput $input, User $user)
 */
class SaveSubmissionDraft
{
    use AsAction;

    private const UPLOAD_DIRECTORY = 'form-uploads';

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(SubmissionAnswersInput $input, User $user): FormSubmission
    {
        AssertFormAccessible::run($user, Form::findOrFail($input->formId), 'Anda tidak memiliki akses ke formulir ini.');

        return DB::transaction(function () use ($input, $user): FormSubmission {
            $submission = $this->editableSubmission($input, $user);

            // BR-FE-14/BR-SM-12 — dua orang yang menyunting pengajuan yang sama tidak boleh saling
            // menimpa diam-diam. Diperiksa di dalam transaksi, setelah baris diresolusi, supaya yang
            // dibandingkan adalah nilai yang benar-benar akan ditimpa.
            OptimisticLock::assertNotStale($submission, $input->lastUpdatedAt);

            $changed = FieldAnswerWriter::overwrite($submission, $input->answers, $input->uploads, self::UPLOAD_DIRECTORY);

            // W161, BR-FE-14 — hanya jawaban yang berubah menggeser cap kunci: tab lain yang membawa cap
            // lama ditolak 409, sedangkan Lanjut tanpa perubahan tidak mengunci siapa pun.
            if ($changed) {
                $submission->touch();
            }

            // BR-SCH-04/BR-SCH-10 (ADR 0005) — pilihan scheme_selector dipindahkan ke kolom scheme_id
            // begitu jawabannya tersimpan. Batas anggaran, anggota, dan tipe luaran menanyakan kolom itu;
            // kalau hanya jadi jawaban form, setiap penanya harus tahu field mana yang menyimpannya.
            SyncSchemeSelection::run($submission);
            SyncTrlSelection::run($submission);
            // BR-SM-22 — jawaban submission_title menjadi judul pengajuan.
            SyncSubmissionTitle::run($submission);

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record('Draft pengajuan disimpan', $submission, $user);

            return $submission;
        });
    }

    /** @throws AuthorizationException */
    private function editableSubmission(SubmissionAnswersInput $input, User $user): FormSubmission
    {
        // #28 — resolver menggantikan firstOrCreate(): baris satu-satunya sumber
        // kebenaran adalah langkah 1 wizard (StartSubmission), bukan simpan atau submit.
        $submission = ResolveWizardSubmission::make()->handle($input->formId, $input->submissionPeriodId, $user);

        // Belum ada baris sama sekali — peneliti melewati langkah 1.
        if ($submission === null) {
            throw new ConflictHttpException('Draf tidak ditemukan; mulai pengajuan dari langkah 1.');
        }

        // BR-SM-05 — isi pengajuan terkunci pada status terminal. Resolver
        // sengaja tidak menyaring status supaya pembedaan 409/403 ini bisa
        // dibuat; [SM-STATE-04] menuntut 403, bukan 409.
        if (!$user->can('update', $submission)) {
            throw new AuthorizationException('Pengajuan ini sudah terkunci.');
        }

        // BR-FE-08 — lewat batas fase, akses ditutup keras kecuali ada
        // FormSubmissionOverride yang masih berlaku untuk pasangan
        // pengajuan+fase ini. Force close periode mengalahkan keduanya.
        PhaseAccessGate::assertOpenFor($submission, $submission->formPhaseDetail);

        return $submission;
    }
}
