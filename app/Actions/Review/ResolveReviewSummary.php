<?php

namespace App\Actions\Review;

use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Support\Audit\AuditTrail;
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

        $tercatat = DB::transaction(function () use ($summary): ReviewSummary {
            $summary->update(['status' => 'resolved']);

            // Menutup ulir terakhir memicu evaluasi ulang: syarat BR-REV-05 bisa terpenuhi saat
            // itu juga, dan transisinya tidak boleh menunggu aksi lain. `form_submission_id`
            // NOT NULL, jadi PHPStan menolak pengecekan null yang tak tercapai.
            EvaluateSubmissionOutcome::run($summary->formSubmission);

            return $summary->refresh();
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Diskusi review ditutup', $tercatat, $user);

        return $tercatat;
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

        // Reviewer pengganti ikut lolos: BR-REV-15 memindahkan `reviewer_id` ulir yang masih
        // terbuka kepadanya, dan tanpa itu ulirnya tidak akan pernah bisa ditutup.
        // BR-SM-07, BR-REV-10 — reviewer yang sudah diganti kehilangan wewenang walau ulirnya miliknya.
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
