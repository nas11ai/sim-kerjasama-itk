<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-19 — penolakan Form yang pernah dipakai ada di `Form::booted()`; audit ditulis dalam
 * transaksi yang sama sehingga hapus yang ditolak tidak meninggalkan jejak.
 *
 * @method static void run(Form $form, User $actor)
 */
class DeleteForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Form $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            AuditTrail::record('Formulir dihapus', $form, $actor, new AuditChange(old: $form->auditSnapshot()));
            $form->delete();
            $form->draft()->delete();
        });
    }
}
