<?php

namespace App\Actions\Form;

use App\Models\FormAccessControl;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menghapus satu kontrol akses formulir; penjaganya sama dengan hapus massal (BR-FE-20).
 *
 * @method static void run(FormAccessControl $control, User $actor)
 */
class RevokeFormAccess
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormAccessControl $control, User $actor): void
    {
        AssertFormAccessUnused::run(new Collection([$control]));

        DB::transaction(function () use ($control, $actor): void {
            AuditTrail::record('Kontrol akses formulir dihapus', $control, $actor, new AuditChange(
                old: $control->auditSnapshot(),
            ));

            $control->delete();
        });
    }
}
