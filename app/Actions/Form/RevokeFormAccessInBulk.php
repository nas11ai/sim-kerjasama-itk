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
        return DB::transaction(function () use ($ids, $actor): int {
            // Dikunci: detail tahap baru yang merujuknya lewat FK menunggu sampai selesai.
            // Tanpa kunci, detail yang lolos sebelum delete ikut terhapus oleh cascade.
            $controls = FormAccessControl::query()->whereKey($ids)->lockForUpdate()->get();

            AssertFormAccessUnused::run($controls);

            foreach ($controls as $control) {
                $change = new AuditChange(old: $control->auditSnapshot());
                AuditTrail::record('Kontrol akses formulir dihapus', $control, $actor, $change);
                $control->delete();
            }

            return $controls->count();
        });
    }
}
