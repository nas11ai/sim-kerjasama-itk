<?php

namespace App\Data\View\Submission\Manage;

use App\Data\View\Review\DecisionReadinessData;
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

    /** `$readiness` dihitung sekali oleh pemanggil, lalu dipakai Policy approve dan reject. */
    public static function forUser(User $user, FormSubmission $submission, DecisionReadinessData $readiness): self
    {
        return new self(
            assignReviewers: $user->can('assignReviewers', $submission),
            replaceReviewer: $user->can('replaceReviewer', $submission),
            grantOverride: $user->can('grantOverride', $submission),
            transferOwnership: $user->can('transferOwnership', $submission),
            approve: $user->can('approve', [$submission, $readiness]),
            reject: $user->can('reject', [$submission, $readiness]),
            withdraw: $user->can('withdraw', $submission),
        );
    }
}
