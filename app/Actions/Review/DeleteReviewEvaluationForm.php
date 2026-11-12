<?php

namespace App\Actions\Review;

use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Komposit sementara G2-W4: hapus kartu = lepas dari langkah lalu hapus formnya, satu transaksi.
 * BR-FE-22 — form yang pernah ditugaskan ditolak DetachEvaluationForm. Hilang di Task 2.
 *
 * @method static void run(ReviewEvaluationForm $form, User $actor)
 */
class DeleteReviewEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationForm $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            $form->stepEvaluationForms()->get()
                ->each(fn (StepEvaluationForm $step) => DetachEvaluationForm::run($step, $actor));

            AuditTrail::record('Form evaluasi dihapus', $form, $actor, new AuditChange(old: $form->auditSnapshot()));
            // Versi, field, dan opsi ikut ter-cascade; tanpa penugasan belum ada jawaban.
            $form->delete();
        });
    }
}
