<?php

namespace App\Actions\Identity;

use App\Models\Reviewer;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-11, BR-REV-09 — "Edit Periode" / "Perpanjang" (OPERATOR-08-04): masa tugas dulu,
 * penunjukan (yang memberi notifikasi) terakhir, dalam satu transaksi. Penolakan duplikat
 * record (BR-IAM-11) atau akun nonaktif (BR-IAM-05) di AppointReviewer membatalkan keduanya.
 *
 * @method static Reviewer run(User $actor, Reviewer $reviewer, string $type, array{start: string, end: ?string} $tenure)
 */
class ReviseReviewerAppointment
{
    use AsAction;

    /**
     * @param  array{start: string, end: ?string}  $tenure
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Reviewer $reviewer, string $type, array $tenure): Reviewer
    {
        $before = $this->snapshot($reviewer);

        DB::transaction(function () use ($reviewer, $type, $tenure): void {
            ChangeReviewerTenure::run($reviewer, $tenure['start'], $tenure['end']);

            if ($reviewer->reviewer_type !== $type) {
                AppointReviewer::run(User::findOrFail($reviewer->user_id), $type);
            }
        });

        $revised = $reviewer->refresh();
        $after = $this->snapshot($revised);

        // BR-RPT-09 — satu keputusan Operator, satu entri; tanpa perubahan tak ada keputusan.
        if ($before !== $after) {
            AuditTrail::record('Penunjukan reviewer direvisi', $revised, $actor, new AuditChange(old: $before, new: $after));
        }

        return $revised;
    }

    /** @return array{reviewer_type: string, start_date: ?string, end_date: ?string} */
    private function snapshot(Reviewer $reviewer): array
    {
        return [
            'reviewer_type' => $reviewer->reviewer_type,
            'start_date' => $reviewer->start_date?->toDateString(),
            'end_date' => $reviewer->end_date?->toDateString(),
        ];
    }
}
