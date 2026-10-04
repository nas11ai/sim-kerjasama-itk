<?php

namespace App\Actions\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Queries\Review\ReviewersWithinTenure;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-07 — saran reviewer untuk satu siklus monev.
 *
 * Isinya reviewer **pengajuan awal** yang masa tugasnya masih berjalan.
 *
 * **Tidak menulis audit (BR-RPT-09).** Kueri saran reviewer. Yang mengubah state adalah AssignReviewer, dan di situlah auditnya ditulis.
 *
 * @method static Collection<int, Reviewer> run(FormSubmission $parent)
 */
class SuggestCycleReviewers implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, Reviewer> */
    public function handle(FormSubmission $parent): Collection
    {
        // **Saran, bukan batas**: tak pernah dipakai sebagai penyaring di Action penugasan —
        // mencampurnya mengubah kemudahan menjadi larangan. Yang masa tugasnya habis tak
        // disarankan: itu mengarahkan Operator ke penugasan yang pasti ditolak BR-REV-09.
        return ReviewersWithinTenure::run()
            ->whereIn(
                'id',
                $parent->submissionReviewers()
                    ->where('status', 'active')
                    ->select('reviewer_id')
            )
            ->with('user:id,name,email')
            ->get();
    }
}
