<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mengaktifkan atau menonaktifkan Form (ADMIN-08-05). Nilai yang sama tidak mengaudit.
 *
 * @method static void run(Form $form, bool $active, User $actor)
 */
class SetFormActive
{
    use AsAction;

    public function handle(Form $form, bool $active, User $actor): void
    {
        if ($form->is_active === $active) {
            return;
        }

        // BR-FE-19 — Form yang dipakai dinonaktifkan, bukan dihapus
        DB::transaction(function () use ($form, $active, $actor): void {
            $form->update(['is_active' => $active]);

            AuditTrail::record(
                $active ? 'Formulir diaktifkan' : 'Formulir dinonaktifkan',
                $form,
                $actor,
                new AuditChange(['is_active' => !$active], ['is_active' => $active]),
            );
        });
    }
}
