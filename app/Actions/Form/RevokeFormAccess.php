<?php

namespace App\Actions\Form;

use App\Models\FormAccessControl;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menghapus satu kontrol akses formulir; ditolak bila dipakai detail tahap (BR-FE-20).
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
        DB::transaction(function () use ($control, $actor): void {
            // Dikunci: detail tahap baru yang merujuknya lewat FK menunggu sampai selesai.
            // Tanpa kunci, detail yang lolos sebelum delete ikut terhapus oleh cascade.
            $locked = FormAccessControl::query()->whereKey($control->id)->lockForUpdate()->get();

            AssertFormAccessUnused::run($locked);

            foreach ($locked as $lockedControl) {
                AuditTrail::record('Kontrol akses formulir dihapus', $lockedControl, $actor, new AuditChange(
                    old: $lockedControl->auditSnapshot(),
                ));

                $lockedControl->delete();
            }
        });
    }
}
