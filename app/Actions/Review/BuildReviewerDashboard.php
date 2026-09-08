<?php

namespace App\Actions\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ringkasan beban kerja satu reviewer untuk layar dasbornya.
 *
 * Sumbernya `SubmissionReviewer` — tabel penugasan. Versi sebelumnya membaca
 * `ReviewSummary`, yaitu catatan diskusi yang baru lahir setelah reviewer
 * mulai bekerja, sehingga penugasan yang belum disentuh tidak pernah muncul
 * di dasbor: justru pekerjaan yang paling perlu ditagih yang hilang dari
 * layar. Yang menentukan "ditugaskan" adalah BR-REV-01, bukan jejak diskusi.
 *
 * Selesai/belum memakai `SubmissionReviewer::SETTLED_EVALUATION`, ambang yang
 * sama dengan `FormSubmission::canProceed()` (BR-MON-02). Dua layar yang
 * menjawab "evaluasi ini sudah kelar?" dengan definisi berbeda adalah cara
 * paling mudah membuat gerbang dan dasbor saling membantah.
 *
 *
 * **Tidak menulis audit (BR-RPT-09).** Ringkasan beban kerja untuk satu layar — tidak menyentuh satu baris pun.
 *
 * @method static array{stats: array<string, int>, assignments: Collection<int, SubmissionReviewer>} run(Reviewer $reviewer)
 */
class BuildReviewerDashboard implements ChangesNoState
{
    use AsAction;

    /**
     * @return array{stats: array<string, int>, assignments: Collection<int, SubmissionReviewer>}
     */
    public function handle(Reviewer $reviewer): array
    {
        $assignments = SubmissionReviewer::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('status', 'active')
            ->with([
                'formSubmission' => fn ($q) => $q->with([
                    'form:id,title',
                    'submittedBy:id,name,email',
                    'formPhaseDetail.phaseType:id,name',
                ]),
            ])
            // `form_submission_id` NOT NULL dan FK-nya ON DELETE CASCADE, jadi
            // penugasan yatim tidak bisa ada — tidak perlu disaring di sini.
            ->latest()
            ->get();

        $selesai = $assignments
            ->filter(fn (SubmissionReviewer $assignment) => in_array(
                $assignment->evaluation_status,
                SubmissionReviewer::SETTLED_EVALUATION,
                true,
            ))
            ->count();

        return [
            'stats' => [
                'total_assigned' => $assignments->count(),
                'completed_reviews' => $selesai,
                'pending_reviews' => $assignments->count() - $selesai,
            ],
            'assignments' => $assignments,
        ];
    }
}
