<?php

namespace App\Actions\Monev;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-07 — saran reviewer untuk satu siklus monev.
 *
 * Isinya reviewer **pengajuan awal** yang masa tugasnya masih berjalan. Yang
 * sudah habis tidak muncul: menyarankannya berarti mengarahkan Operator ke
 * penugasan yang pasti ditolak BR-REV-09.
 *
 * Ini **saran, bukan batas**. Operator tetap bebas memilih nama lain — dan
 * itulah sebabnya daftar ini tidak pernah dipakai sebagai penyaring di Action
 * penugasan. Mencampur keduanya akan mengubah kemudahan menjadi larangan.
 *
 * @method static Collection<int, Reviewer> run(FormSubmission $parent)
 */
class SuggestCycleReviewers
{
    use AsAction;

    /** @return Collection<int, Reviewer> */
    public function handle(FormSubmission $parent): Collection
    {
        $now = now();

        return Reviewer::query()
            ->whereIn(
                'id',
                $parent->submissionReviewers()
                    ->where('status', 'active')
                    ->select('reviewer_id')
            )
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $now))
            ->with('user:id,name,email')
            ->get();
    }
}
