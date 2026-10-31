<?php

namespace App\Data\Submission;

use App\Models\FormSubmission;
use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * Jawaban Policy atas satu pengajuan, supaya frontend berhenti menebak.
 * Menyusun ulang aturan di Vue melewatkan arsip periode (BR-SM-10) dan
 * status terminal (BR-SM-05); tebakan itu selalu lebih longgar dari aturannya.
 */
class SubmissionAbilitiesData extends Data
{
    public function __construct(
        public bool $view,
        public bool $update,
        public bool $submit,
        public bool $resubmit,
        public bool $withdraw,
        public bool $manageTargetOutput,
        public bool $discussReview,
    ) {}

    public static function forUser(User $user, FormSubmission $submission): self
    {
        return new self(
            view: $user->can('view', $submission),
            update: $user->can('update', $submission),
            submit: $user->can('submit', $submission),
            // BR-SM-03 — tindakan tersendiri: hanya NEEDS_REVISION, dan
            // tombolnya berbeda dari Submit di layar.
            resubmit: $user->can('resubmit', $submission),
            withdraw: $user->can('withdraw', $submission),
            // BR-SM-15 — hanya ketua pengaju, sampai REJECTED atau WITHDRAWN.
            manageTargetOutput: $user->can('manageTargetOutput', $submission),
            // BR-REV-06 — kotak balas ulir; sumbernya sama dengan AddReviewComment.
            discussReview: $user->can('discussReview', $submission),
        );
    }
}
