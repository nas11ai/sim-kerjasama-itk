<?php

namespace App\Actions\Period;

use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodClosedNotification;
use App\Queries\Period\PeriodDraftOwners;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Close Early — Operator menutup periode **sebelum** semua tenggatnya terlewat
 * (ubiquitous language, "Period Lifecycle"). BR-SCH-07: seluruh fasenya terkunci
 * seketika; BR-SM-10: pemilik draft diberi tahu (event SubmissionPeriodClosed).
 *
 * @method static int run(SubmissionPeriod $period, User $actor)
 */
class CloseSubmissionPeriod
{
    use AsAction;

    /**
     * @return int jumlah pemilik draft yang diberi tahu
     *
     * @throws ValidationException
     */
    public function handle(SubmissionPeriod $period, User $actor): int
    {
        // W68 — periode yang sudah tertutup (ditutup Operator atau tenggatnya habis) tidak
        // ditutup lagi: tanpa penjaga ini pemilik draft menerima pemberitahuan kedua.
        if (SubmissionPeriodGate::isFullyClosed($period)) {
            throw ValidationException::withMessages(['period' => 'Periode ini sudah tertutup.']);
        }

        return DB::transaction(function () use ($period, $actor): int {
            $period->update(['is_force_closed' => true]);
            $owners = PeriodDraftOwners::run($period);

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record('Periode pengajuan ditutup', $period, $actor);

            DB::afterCommit(static function () use ($owners, $period): void {
                Deliver::to($owners, new SubmissionPeriodClosedNotification($period));
            });

            return $owners->count();
        });
    }
}
