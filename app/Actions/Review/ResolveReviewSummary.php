<?php

namespace App\Actions\Review;

use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Notifications\RevisionResolvedNotification;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-06 — menutup satu ulir diskusi.
 *
 * @method static ReviewSummary run(ReviewSummary $summary, User $user)
 */
class ResolveReviewSummary
{
    use AsAction;

    /**
     * @throws AuthorizationException
     */
    public function handle(ReviewSummary $summary, User $user): ReviewSummary
    {
        $this->guardReviewer($summary, $user);

        // Ulir yang sudah ditutup tidak diproses ulang: evaluasi, audit, dan notifikasinya
        // sudah terjadi sekali, dan mengulangnya hanya menggandakan jejak.
        if ($summary->status !== 'open') {
            return $summary;
        }

        $resolved = DB::transaction(function () use ($summary): ReviewSummary {
            $summary->update(['status' => 'resolved']);

            // Menutup ulir terakhir memicu evaluasi ulang: syarat BR-REV-05 bisa terpenuhi saat
            // itu juga, dan transisinya tidak boleh menunggu aksi lain. `form_submission_id`
            // NOT NULL, jadi PHPStan menolak pengecekan null yang tak tercapai.
            EvaluateSubmissionOutcome::run($summary->formSubmission);

            return $summary->refresh();
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Diskusi review ditutup', $resolved, $user);

        // BR-NOT-02, BR-NOT-03 — peneliti utama diberi kabar in-app, setelah tersimpan.
        $submission = $resolved->formSubmission;
        Deliver::to($submission->submittedBy, new RevisionResolvedNotification($submission));

        return $resolved;
    }

    /**
     * Yang boleh menutup adalah **reviewer**, bukan researcher: kalau researcher bisa menandai
     * catatannya sendiri selesai, ia memaksa pengajuannya ke APPROVED tanpa persetujuan siapa
     * pun — dan itu justru yang dijaga `[REV-DISCUSS-04]`.
     *
     * @throws AuthorizationException
     */
    private function guardReviewer(ReviewSummary $summary, User $user): void
    {
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        // BR-REV-15 — ReplaceReviewer memindahkan `reviewer_id` ulir terbuka ke pengganti,
        // jadi ia ikut lolos; tanpa itu ulir warisan tak bisa ditutup. BR-SM-07, BR-REV-10 —
        // reviewer yang sudah diganti tak lagi berwenang, meski namanya di ulir.
        if (
            $reviewer !== null
            && $summary->reviewer_id === $reviewer->id
            && ActiveReviewerAssignment::run($summary->formSubmission, $reviewer->id) !== null
        ) {
            return;
        }

        throw new AuthorizationException(
            'Hanya reviewer pemilik catatan ini yang dapat menandainya selesai.'
        );
    }
}
