<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Review\AwaitingDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-10 — mengganti reviewer pada satu siklus.
 */
class ReplaceReviewer
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormSubmission $submission, Reviewer $outgoing, Reviewer $incoming, ?string $note): SubmissionReviewer
    {
        AwaitingDecision::guard($submission);

        $current = $this->activeSeat($submission, $outgoing);

        $tercatat = DB::transaction(function () use ($submission, $outgoing, $incoming, $current): SubmissionReviewer {
            // Record lama ditandai `replaced`, **tidak dihapus**: evaluasinya adalah alasan
            // di balik keputusan yang mungkin sudah diambil, dan menghapusnya menghilangkan
            // jejak itu.
            $current->markAsReplaced();

            // AssignReviewer memegang seluruh syarat penugasan — konflik
            // kepentingan, masa tugas, duplikat. Memanggilnya di sini berarti
            // penggantian tunduk pada aturan yang sama dengan penugasan baru.
            $fresh = AssignReviewer::run($submission, $incoming);

            // BR-REV-15 — ulir diskusi yang masih terbuka diwarisi penggantinya. Tanpa itu ulir
            // tersebut tidak akan pernah bisa ditutup: penulis lama sudah tidak berwenang,
            // pengganti tidak merasa berhak, dan submission terkunci di NEEDS_REVISION selamanya.
            $submission->reviewSummaries()
                ->where('reviewer_id', $outgoing->id)
                // Yang sudah `resolved` sengaja tidak ikut — ia tidak menuntut siapa pun, dan
                // mengalihkannya justru mengaburkan siapa yang dulu menutupnya.
                ->where('status', 'open')
                ->update(['reviewer_id' => $incoming->id]);

            return $fresh;
        });

        $this->audit($tercatat, $note);

        return $tercatat;
    }

    /** @throws ValidationException */
    private function activeSeat(FormSubmission $submission, Reviewer $outgoing): SubmissionReviewer
    {
        $current = $submission->submissionReviewers()
            ->where('reviewer_id', $outgoing->id)
            ->where('status', 'active')
            ->first();

        if ($current === null) {
            throw ValidationException::withMessages([
                'reviewer_id' => 'Reviewer yang hendak diganti tidak sedang bertugas pada pengajuan ini.',
            ]);
        }

        return $current;
    }

    // BR-RPT-09 — satu Action, satu entri audit; catatan opsional ikut di entri itu (W26).
    private function audit(SubmissionReviewer $fresh, ?string $note): void
    {
        $change = ($note === null || $note === '') ? null : new AuditChange(new: ['note' => $note]);

        AuditTrail::record('Reviewer diganti', $fresh, change: $change);
    }
}
