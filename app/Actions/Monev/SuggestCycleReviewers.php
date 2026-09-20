<?php

namespace App\Actions\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\Reviewer;
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
        $now = now();

        // **Saran, bukan batas**: daftar ini tidak pernah dipakai sebagai penyaring
        // di Action penugasan — mencampurnya mengubah kemudahan menjadi larangan.
        return Reviewer::query()
            ->whereIn(
                'id',
                $parent->submissionReviewers()
                    ->where('status', 'active')
                    ->select('reviewer_id')
            )
            // Menyarankan yang masa tugasnya habis berarti mengarahkan
            // Operator ke penugasan yang pasti ditolak BR-REV-09.
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $now))
            ->with('user:id,name,email')
            ->get();
    }
}
