<?php

namespace App\Data\View\Submission\Manage;

use App\Models\FormSubmission;
use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * Aksi Operator yang tersedia di OPERATOR-02-02. Dibaca dari Policy yang sama dengan
 * endpoint-nya, jadi tombol tidak tampil untuk aksi yang pasti ditolak (ADR 0015).
 */
final class OperatorAbilitiesData extends Data
{
    public function __construct(
        public bool $assignReviewers,
        public bool $replaceReviewer,
        public bool $grantOverride,
        public bool $transferOwnership,
        public bool $approve,
        public bool $reject,
        public bool $withdraw,
    ) {}

    public static function forUser(User $user, FormSubmission $submission): self
    {
        return new self(
            assignReviewers: $user->can('assignReviewers', $submission),
            replaceReviewer: $user->can('replaceReviewer', $submission),
            grantOverride: $user->can('grantOverride', $submission),
            transferOwnership: $user->can('transferOwnership', $submission),
            approve: $user->can('approve', $submission),
            reject: $user->can('reject', $submission),
            withdraw: $user->can('withdraw', $submission),
        );
    }
}
