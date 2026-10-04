<?php

namespace App\Actions\Identity;

use App\Models\Reviewer;
use App\Models\User;
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
            // BR-IAM-11 — bisa ada duplikat lama; role dicabut hanya bila tak ada record
            // lain milik user ini, supaya yang tersisa tetap reviewer (BR-REV-11).
            $hasOtherRecord = Reviewer::where('user_id', $reviewer->user_id)
                ->whereKeyNot($reviewer->id)->exists();

            if (!$hasOtherRecord) {
                $this->revokeReviewerRoles($reviewer->user);
            }

            // BR-RPT-09 — dicatat sebelum record hilang agar subjek audit masih utuh.
            AuditTrail::record('Reviewer dihapus', $reviewer);

            $reviewer->delete();
        });
    }

    // removeRole() melempar RoleDoesNotExist bila role belum pernah dibuat; cabut
    // hanya yang benar-benar dipegang user.
    private function revokeReviewerRoles(?User $user): void
    {
        foreach (AppointReviewer::ROLES as $role) {
            if ($user?->hasRole($role)) {
                $user->removeRole($role);
            }
        }
    }
}
