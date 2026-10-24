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
 * BR-FE-22 — penolakan form evaluasi yang pernah ditugaskan ada di
 * `ReviewEvaluationForm::booted()`; audit ditulis dalam transaksi yang sama sehingga hapus
 * yang ditolak tidak meninggalkan jejak.
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
            AuditTrail::record('Form evaluasi dihapus', $form, $actor, new AuditChange(old: $form->auditSnapshot()));
            // Field dan opsinya ikut terhapus lewat cascade basis data; tanpa penugasan, belum ada
            // jawaban reviewer yang ikut hilang.
            $form->delete();
        });
    }
}
