<?php

namespace App\Actions\Review;

use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Urutan baru form evaluasi dari seret-lepas; hanya form yang urutannya berubah yang
 * dicatat jejak auditnya.
 *
 * @method static void run(array<int, int> $orders, User $actor)
 */
class ReorderReviewEvaluationForms
{
    use AsAction;

    /** @param  array<int, int>  $orders  id form evaluasi => urutannya */
    public function handle(array $orders, User $actor): void
    {
        DB::transaction(function () use ($orders, $actor): void {
            foreach (ReviewEvaluationForm::query()->whereKey(array_keys($orders))->get() as $form) {
                $old = $form->order;
                $form->update(['order' => $orders[$form->id]]);

                if ($form->wasChanged('order')) {
                    AuditTrail::record('Urutan form evaluasi diubah', $form, $actor, new AuditChange(['order' => $old], ['order' => $form->order]));
                }
            }
        });
    }
}
