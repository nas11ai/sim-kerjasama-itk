<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-10 — mengganti reviewer pada satu siklus.
 *
 * Record lama ditandai `replaced`, **tidak dihapus**: evaluasinya adalah
 * alasan di balik keputusan yang mungkin sudah diambil, dan menghapusnya
 * menghilangkan jejak itu.
 *
 * BR-REV-15 — ulir diskusi yang masih `open` diwarisi penggantinya. Tanpa
 * pewarisan ini summary tersebut tidak akan pernah bisa ditutup: penulis
 * lama sudah tidak berwenang, pengganti tidak merasa berhak, dan submission
 * terkunci di NEEDS_REVISION selamanya. Yang sudah `resolved` sengaja tidak
 * ikut — ia tidak menuntut siapa pun, dan mengalihkannya justru mengaburkan
 * siapa yang dulu menutupnya.
 */
class ReplaceReviewer
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormSubmission $submission, Reviewer $outgoing, Reviewer $incoming): SubmissionReviewer
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

        $tercatat = DB::transaction(function () use ($submission, $outgoing, $incoming, $current): SubmissionReviewer {
            $current->markAsReplaced();

            // AssignReviewer memegang seluruh syarat penugasan — konflik
            // kepentingan, masa tugas, duplikat. Memanggilnya di sini berarti
            // penggantian tunduk pada aturan yang sama dengan penugasan baru.
            $fresh = AssignReviewer::run($submission, $incoming);

            $submission->reviewSummaries()
                ->where('reviewer_id', $outgoing->id)
                ->where('status', 'open')
                ->update(['reviewer_id' => $incoming->id]);

            return $fresh;
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Reviewer diganti', $tercatat);

        return $tercatat;
    }
}
