<?php

namespace App\Actions\Review;

use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-06 — menutup satu ulir diskusi.
 *
 * Yang boleh menutup adalah **reviewer**, bukan researcher. Kalau researcher
 * bisa menandai catatannya sendiri selesai, ia bisa memaksa pengajuannya
 * berpindah ke APPROVED tanpa persetujuan siapa pun — dan itu justru yang
 * dijaga `[REV-DISCUSS-04]`.
 *
 * Reviewer pengganti ikut berwenang, karena BR-REV-15 mewariskan ulir yang
 * masih terbuka kepadanya. Tanpa itu ulir tersebut tidak akan pernah bisa
 * ditutup dan pengajuannya terkunci di NEEDS_REVISION.
 *
 * Menutup ulir terakhir memicu evaluasi ulang: syarat BR-REV-05 bisa jadi
 * terpenuhi saat itu juga, dan transisinya tidak boleh menunggu aksi lain.
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

        return DB::transaction(function () use ($summary): ReviewSummary {
            $summary->update(['status' => 'resolved']);

            // form_submission_id NOT NULL, jadi relasi ini selalu ada —
            // PHPStan menolak pengecekan null di sini karena tidak pernah
            // tercapai.
            EvaluateSubmissionOutcome::run($summary->formSubmission);

            return $summary->refresh();
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function guardReviewer(ReviewSummary $summary, User $user): void
    {
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if ($reviewer !== null && $summary->reviewer_id === $reviewer->id) {
            return;
        }

        throw new AuthorizationException(
            'Hanya reviewer pemilik catatan ini yang dapat menandainya selesai.'
        );
    }
}
