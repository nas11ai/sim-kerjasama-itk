<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Illuminate\Contracts\Database\Eloquent\Builder;

class BudgetLineItemPolicy
{
    public function update(User $user, FormSubmission $submission): bool
    {
        // BR-BUD-02 — sumbu keadaan lebih dulu: status terminal mengunci
        // anggaran bagi siapa pun, termasuk ketua pengaju.
        if (self::lockedByState($submission)) {
            return false;
        }

        // BR-BUD-09 — menyusun anggaran adalah pekerjaan bersama; mengikat diri
        // lewat submit tetap hanya hak submitted_by (BR-SM-04).
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        // ResearchMember menunjuk UserProfile, bukan User — ditelusuri sama seperti
        // FormSubmission::hasActiveResearchMember(). Role `member` read-only.
        return $submission->researchMembers()
            ->where('status', 'active')
            ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
            ->whereHas('userProfile', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->exists();
    }

    /**
     * NEEDS_REVISION sengaja **tidak** di sini: BR-BUD-03 menuntut anggaran
     * tetap bisa disunting saat revisi. Publik supaya layar bisa membedakan
     * "terkunci status" dari "hanya baca karena peran" tanpa salinan kedua.
     */
    public static function lockedByState(FormSubmission $submission): bool
    {
        return $submission->status instanceof Approved
            || $submission->status instanceof Rejected
            || $submission->status instanceof Withdrawn;
    }
}
