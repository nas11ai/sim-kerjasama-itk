<?php

namespace App\Actions\Override;

use App\Models\FormSubmissionOverride;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-08 — mencabut perpanjangan menutup kembali aksesnya.
 *
 * Record-nya tidak dihapus, hanya ditandai tidak aktif: pengecualian yang
 * pernah diberikan adalah bagian dari riwayat keputusan, dan menghapusnya
 * berarti menghapus pertanggungjawabannya.
 *
 * @method static FormSubmissionOverride run(FormSubmissionOverride $override)
 */
class RevokePhaseOverride
{
    use AsAction;

    public function handle(FormSubmissionOverride $override): FormSubmissionOverride
    {
        $override->revoke();

        return $override->refresh();
    }
}
