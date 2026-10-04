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
 * BR-FE-20 — hapus massal semua-atau-tidak: satu kontrol akses yang dipakai menolak seluruh
 * pilihan, sebelum apa pun disentuh. Dulu yang bisa dihapus tetap terhapus diam-diam.
 *
 * @method static int run(list<int> $ids, User $actor)
 */
class RevokeFormAccessInBulk
{
    use AsAction;

    /**
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    public function handle(array $ids, User $actor): int
    {
        $controls = FormAccessControl::query()->whereKey($ids)->get();

        AssertFormAccessUnused::run($controls);

        return DB::transaction(function () use ($controls, $actor): int {
            foreach ($controls as $control) {
                $change = new AuditChange(old: $control->auditSnapshot());
                AuditTrail::record('Kontrol akses formulir dihapus', $control, $actor, $change);
                $control->delete();
            }

            return $controls->count();
        });
    }
}
