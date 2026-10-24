<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pihak yang membaca ulir diskusi satu pengajuan: operator, reviewer yang
 * penugasannya masih aktif, dan pengaju. Reviewer yang sudah diganti tidak lagi
 * membaca diskusinya (BR-SM-07, BR-REV-10).
 *
 * @method static Collection<int, User> run(FormSubmission $submission, ?int $exceptUserId = null)
 */
class DiscussionParticipants implements ChangesNoState
{
    use AsAction;

    /**
     * Operator dikenali dari permission, bukan nama role — lewat `whereHas`, bukan
     * scope `permission()` Spatie yang melempar bila permission-nya belum dibuat.
     */
    private const OPERATOR_PERMISSION = 'submissions.view-all';

    /** @return Collection<int, User> */
    public function handle(FormSubmission $submission, ?int $exceptUserId = null): Collection
    {
        $reviewerUserIds = Reviewer::query()
            ->whereHas('submissionReviewers', fn (Builder $assignment) => $assignment
                ->where('form_submission_id', $submission->id)
                ->where('status', 'active'))
            ->select('user_id');

        $isOperator = fn (Builder $permission) => $permission->where('name', self::OPERATOR_PERMISSION);

        return User::query()
            ->where(fn (Builder $user) => $user
                ->whereKey($submission->submitted_by)
                ->orWhereIn('id', $reviewerUserIds)
                ->orWhereHas('permissions', $isOperator)
                ->orWhereHas('roles.permissions', $isOperator))
            ->when($exceptUserId !== null, fn (Builder $user) => $user->whereKeyNot($exceptUserId))
            ->get();
    }
}
