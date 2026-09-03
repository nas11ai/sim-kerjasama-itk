<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;

class FormSubmissionPolicy
{
    /**
     * BR-SM-07 — akses level submission, lapis terpisah dari FormAccessControl.
     */
    public function view(User $user, FormSubmission $submission): bool
    {
        return $submission->isAccessibleBy($user);
    }

    public function update(User $user, FormSubmission $submission): bool
    {
        // I6 — guard isAccessibleBy() dihapus: cabang submitted_by di
        // dalamnya sudah tercakup oleh cek submitted_by di bawah, jadi guard
        // ini tidak pernah mengubah hasil tapi menambah dua query exists().
        // Hanya lead researcher yang boleh mengubah isi pengajuan.
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        return $submission->status instanceof Draft
            || $submission->status instanceof NeedsRevision;
    }

    public function submit(User $user, FormSubmission $submission): bool
    {
        // BR-SM-04 — hanya submitted_by yang boleh submit dan resubmit.
        return $submission->submitted_by === $user->id;
    }

    public function withdraw(User $user, FormSubmission $submission): bool
    {
        // BR-SM-11 — withdrawal hanya oleh Operator, bukan self-service.
        return $user->can('submissions.view-all')
            && $submission->status instanceof Approved;
    }
}
