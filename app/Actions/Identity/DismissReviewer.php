<?php

namespace App\Actions\Identity;

use App\Models\Reviewer;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-10 — reviewer yang pernah ditugaskan (termasuk `replaced` atau selesai) tidak
 * dihapus: penugasannya adalah riwayat. Penghapusan mencabut role reviewer_* (BR-REV-11);
 * tanpa itu akunnya tetap diperlakukan sebagai reviewer padahal recordnya sudah tiada.
 *
 * @method static void run(Reviewer $reviewer)
 */
class DismissReviewer
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Reviewer $reviewer): void
    {
        if ($reviewer->submissionReviewers()->exists()) {
            throw ValidationException::withMessages([
                'error' => 'Tidak dapat menghapus reviewer yang masih memiliki riwayat penugasan.',
            ]);
        }

        DB::transaction(function () use ($reviewer): void {
            $reviewer->user?->removeRole(...array_values(AppointReviewer::ROLES));

            // BR-RPT-09 — dicatat sebelum record hilang agar subjek audit masih utuh.
            AuditTrail::record('Reviewer dihapus', $reviewer);

            $reviewer->delete();
        });
    }
}
