<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pemilik draft **aktif** satu periode: pengajuan yang sudah dimulai tapi belum
 * dikirim. Penerima BR-SCH-08 (perpanjangan) dan BR-SM-10 (periode ditutup);
 * pengajuan yang sudah masuk tidak terdampak batas pengisian.
 *
 * @method static Collection<int, User> run(SubmissionPeriod $period)
 */
class PeriodDraftOwners implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, User> */
    public function handle(SubmissionPeriod $period): Collection
    {
        return User::query()
            ->whereIn('id', FormSubmission::query()
                ->where('submission_period_id', $period->id)
                // BR-SM-10 (v2.5) — hanya draft pengajuan; laporan monev tidak terdampak Close Early.
                ->whereNull('parent_submission_id')
                ->where('is_submitted', false)
                ->select('submitted_by'))
            ->get();
    }
}
