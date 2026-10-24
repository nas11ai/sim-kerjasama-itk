<?php

namespace App\Policies;

use App\Models\ReviewerFormAssignment;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;

class ReviewerFormAssignmentPolicy
{
    /**
     * BR-SM-07, BR-REV-10 — admin membaca penugasan siapa pun. Selebihnya hanya reviewernya,
     * dan form milik penugasan yang sudah diganti tertutup, termasuk untuk dibaca.
     */
    public function view(User $user, ReviewerFormAssignment $assignment): bool
    {
        return $user->can('users.manage') || $this->evaluate($user, $assignment);
    }

    /**
     * Menjawab evaluasi atas nama reviewernya. Admin tidak ikut: record akan menyatakan
     * reviewer menilai padahal bukan dia — alasan ADR 0015 memasukkannya ke INVARIANT_ABILITIES.
     */
    public function evaluate(User $user, ReviewerFormAssignment $assignment): bool
    {
        $submissionReviewer = $assignment->submissionReviewer;

        if ($submissionReviewer->reviewer->user_id !== $user->id) {
            return false;
        }

        $active = ActiveReviewerAssignment::run($submissionReviewer->formSubmission, $submissionReviewer->reviewer_id);

        return $active?->is($submissionReviewer) === true;
    }
}
