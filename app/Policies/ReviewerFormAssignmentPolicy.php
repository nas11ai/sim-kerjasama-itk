<?php

namespace App\Policies;

use App\Models\ReviewerFormAssignment;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;

class ReviewerFormAssignmentPolicy
{
    /**
     * Menjawab evaluasi atas nama reviewernya. Admin tidak ikut: record akan menyatakan
     * reviewer menilai padahal bukan dia — alasan ADR 0015 memasukkannya ke INVARIANT_ABILITIES.
     */
    public function evaluate(User $user, ReviewerFormAssignment $assignment): bool
    {
        // BR-FE-17 — tenggat evaluasi mengikat reviewer sendiri; lewat tenggat isian tertutup
        // (FE-WINDOW-02) kecuali dibuka override (W25). Bacaan lembar ada di ReviewerEvaluation.
        // BR-REV-10 — penugasan yang sudah diganti tertutup (ownsActive).
        return !$assignment->isClosedByDeadline() && $this->ownsActive($user, $assignment);
    }

    private function ownsActive(User $user, ReviewerFormAssignment $assignment): bool
    {
        $submissionReviewer = $assignment->submissionReviewer;

        if ($submissionReviewer->reviewer->user_id !== $user->id) {
            return false;
        }

        $active = ActiveReviewerAssignment::run($submissionReviewer->formSubmission, $submissionReviewer->reviewer_id);

        return $active?->is($submissionReviewer) === true;
    }
}
