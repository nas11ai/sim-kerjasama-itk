<?php

namespace App\Actions\Review;

use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menghapus Form Evaluasi dari pustaka beserta versi (cascade) dan drafnya, satu transaksi.
 * BR-FE-22 — penjaga model menolak form yang pernah ditugaskan atau masih terpasang di langkah.
 *
 * @method static void run(ReviewEvaluationForm $form, User $actor)
 */
class DeleteEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationForm $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            AuditTrail::record('Form evaluasi dihapus', $form, $actor, new AuditChange(old: $form->auditSnapshot()));
            // Draf tak ber-FK ke form (G2-W7), jadi dihapus di sini; penolakan penjaga membatalkannya.
            $form->draft()->delete();
            $form->delete();
        });
    }
}
