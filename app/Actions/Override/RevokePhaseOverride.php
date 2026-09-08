<?php

namespace App\Actions\Override;

use App\Models\FormSubmissionOverride;
use App\Support\Audit\AuditTrail;
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

        $tercatat = $override->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Perpanjangan akses fase dicabut', $tercatat);

        return $tercatat;
    }
}
