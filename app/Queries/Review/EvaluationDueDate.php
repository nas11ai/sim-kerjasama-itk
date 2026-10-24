<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tenggat bawaan penugasan yang dimulai reviewer sendiri: tanggal terakhir periode pengajuannya,
 * atau seminggu lagi bila periode itu tak bertanggal. Dulu dicari dari periode mana pun yang
 * memakai formnya — form dipakai ulang lintas periode, jadi tenggatnya bisa sudah lewat.
 *
 * @method static Carbon run(FormSubmission $submission)
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): Carbon
    {
        $latest = $submission->submissionPeriod?->submissionDates()->orderByDesc('datetime')->first();

        return $latest === null ? now()->addWeek() : $latest->datetime;
    }
}
