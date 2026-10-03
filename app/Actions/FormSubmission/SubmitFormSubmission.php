<?php

namespace App\Actions\FormSubmission;

use App\Events\ProposalSubmitted;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Rejected;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\Withdrawn;
use App\Support\AccountStateGuard;
use App\Support\Audit\AuditTrail;
use App\Support\RequiredFieldGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SubmitFormSubmission
{
    use AsAction;

    /** SQLSTATE PostgreSQL untuk pelanggaran unique constraint. */
    private const UNIQUE_VIOLATION = '23505';

    public function handle(FormSubmission $submission, User $user): FormSubmission
    {
        $status = $this->guardSubmittable($submission, $user);

        // BR-IAM-04/BR-IAM-13 — akun yang belum terverifikasi (atau yang
        // verifikasinya ditolak) boleh menyiapkan draft, tapi tidak boleh
        // mengirimnya. Diperiksa sebelum kelengkapan field: yang menghalangi
        // di sini bukan isian pengajuannya melainkan keadaan akunnya, dan
        // pesan itulah yang perlu dibaca peneliti.
        AccountStateGuard::assertVerified($user);

        // BR-SM-02 + BR-FE-11 — seluruh field wajib, disaring Temporal Field Binding.
        RequiredFieldGuard::assertComplete($submission);
        $this->guardActiveSubmissionUniqueness($submission);

        $this->markSubmitted($submission);

        $status->transitionTo(Submitted::class);

        // Refresh dulu supaya listener yang membaca ulang baris melihat status
        // SUBMITTED yang sudah tersimpan, baru kemudian event di-dispatch.
        $submission->refresh();

        ProposalSubmitted::dispatch($submission);

        $tercatat = $submission;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Pengajuan disubmit', $tercatat, $user);

        return $tercatat;
    }

    /**
     * @throws AuthorizationException
     */
    private function guardSubmittable(FormSubmission $submission, User $user): SubmissionStatus
    {
        // BR-SM-04 — dijaga FormSubmissionPolicy::submit(); dipanggil di sini
        // supaya aturannya juga berlaku di jalur yang tidak lewat HTTP, dan
        // supaya aturannya hanya tertulis di satu tempat. `submit` ada di
        // INVARIANT_ABILITIES, jadi Gate::before sengaja tidak melewatkan
        // Super Admin dan Policy-lah yang memutuskan (ADR 0015).
        if (!$user->can('submit', $submission)) {
            throw new AuthorizationException(
                'Hanya lead researcher yang bisa submit pengajuan ini.'
            );
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — tidak bisa disubmit."
            );
        }

        return $status;
    }

    /**
     * @throws ValidationException
     * @throws QueryException
     */
    private function markSubmitted(FormSubmission $submission): void
    {
        // BR-SM-16 — index one_active_submission_per_period_scheme adalah jaring
        // pengaman lomba-balap: dua submit bersamaan lolos pemeriksaan di atas,
        // lalu salah satunya dibentur di sini. Peneliti tidak boleh melihat galat
        // database mentah, jadi pelanggarannya diterjemahkan jadi pesan yang sama.
        try {
            // Transaksi bersarang = SAVEPOINT. Tanpa itu, 23505 membuat
            // PostgreSQL membatalkan seluruh transaksi, dan query pemulihan di
            // bawah gagal dengan 25P02 — peneliti tetap dapat 500, persis yang
            // BR-SM-16 larang. ROLLBACK TO SAVEPOINT membuang tulisan yang
            // gagal saja dan meninggalkan koneksinya tetap terpakai.
            DB::transaction(function () use ($submission): void {
                $submission->update([
                    'is_submitted' => true,
                ]);
            });
        } catch (QueryException $e) {
            // errorInfo[0] adalah SQLSTATE apa adanya dari driver; getCode()
            // melewati pengubahan tipe PDOException dan tidak bisa dipercaya.
            if (($e->errorInfo[0] ?? null) !== self::UNIQUE_VIOLATION) {
                throw $e;
            }

            $clash = $this->activeSubmissionClash($submission);

            throw $clash === null
                ? $e
                : $this->activeSubmissionRejection($clash);
        }
    }

    /**
     * BR-SM-01 — pengajuan aktif yang menghalangi, kalau ada. Cermin persis predikat
     * index `one_active_submission_per_period_scheme`.
     *
     * `scheme_id` atau `submission_period_id` yang NULL tidak pernah dianggap
     * duplikat — semantik bawaan unique index PostgreSQL, dan BR-SM-01 memang
     * mengizinkan dua pengajuan tanpa skema hidup bersamaan.
     */
    private function activeSubmissionClash(FormSubmission $submission): ?FormSubmission
    {
        if ($submission->scheme_id === null || $submission->submission_period_id === null) {
            return null;
        }

        return FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->where('is_submitted', true)
            ->whereNotIn('status', [Rejected::$name, Withdrawn::$name])
            ->where('submitted_by', $submission->submitted_by)
            ->where('submission_period_id', $submission->submission_period_id)
            ->where('scheme_id', $submission->scheme_id)
            ->whereKeyNot($submission->getKey())
            ->first();
    }

    /**
     * BR-SM-16 — penolakan harus bisa ditindaklanjuti: sebut pengajuan yang
     * menghalangi beserta tautannya, jangan sekadar "gagal".
     *
     * @throws ValidationException
     */
    private function guardActiveSubmissionUniqueness(FormSubmission $submission): void
    {
        $clash = $this->activeSubmissionClash($submission);

        if ($clash === null) {
            return;
        }

        throw $this->activeSubmissionRejection($clash);
    }

    private function activeSubmissionRejection(FormSubmission $clash): ValidationException
    {
        return ValidationException::withMessages([
            'scheme_id' => sprintf(
                'Anda sudah punya pengajuan aktif pada skema ini: #%d (%s). '
                .'Tarik atau selesaikan pengajuan itu dulu sebelum mengajukan yang baru.',
                $clash->id,
                route('user.submissions.show', $clash)
            ),
        ]);
    }
}
