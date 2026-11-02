<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Identity\DeactivationBlockerData;
use App\Data\View\Identity\UserAbilitiesData;
use App\Data\View\Identity\UserAccessData;
use App\Data\View\Identity\UserDetailData;
use App\Data\View\Identity\UserReviewRowData;
use App\Data\View\Identity\UserSubmissionRowData;
use App\Data\View\Identity\UserSummaryData;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-08-03 — detail user. Riwayatnya tetap terbaca setelah deaktivasi (IAM-DEACT-03),
 * dan riwayat reviewer memuat penugasan yang sudah diganti (BR-REV-10).
 *
 * @phpstan-type Detail array{user: UserDetailData, access: UserAccessData, summary: UserSummaryData, deactivationBlockers: list<DeactivationBlockerData>, submissions: list<UserSubmissionRowData>, reviews: list<UserReviewRowData>, tab: string, can: UserAbilitiesData}
 *
 * @method static Detail run(User $viewer, User $user, string $tab)
 */
class UserDetail implements ChangesNoState
{
    use AsAction;

    private const TABS = ['profile', 'submissions', 'reviews'];

    /** @return Detail */
    public function handle(User $viewer, User $user, string $tab): array
    {
        $user->loadMissing(['userProfile', 'organization', 'roles', 'permissions']);
        $managesSubmissions = $viewer->can('submissions.view-all');
        $blockers = DeactivationBlockers::run($user);

        return [
            'user' => UserDetailData::fromModel($user),
            'access' => UserAccessData::fromModel($user),
            'summary' => new UserSummaryData(
                activeLeadSubmissions: $blockers->count(),
                runningReviews: $this->assignments($user)->unsettledEvaluation()->count(),
                registeredAt: $user->created_at?->toIso8601String(),
            ),
            'deactivationBlockers' => array_values($blockers
                ->map(fn (FormSubmission $s): DeactivationBlockerData => DeactivationBlockerData::fromModel($s, $managesSubmissions))
                ->all()),
            'submissions' => $this->submissions($user, $managesSubmissions),
            'reviews' => array_values($this->assignments($user)
                ->with('formSubmission.form:id,title')
                ->latest('id')
                ->get()
                ->map(fn (SubmissionReviewer $seat): UserReviewRowData => UserReviewRowData::fromModel($seat))
                ->all()),
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'profile',
            'can' => UserAbilitiesData::for($viewer, $user),
        ];
    }

    /** @return Builder<SubmissionReviewer> semua penugasan record reviewer milik user, termasuk `replaced`. */
    private function assignments(User $user): Builder
    {
        return SubmissionReviewer::query()->whereHas('reviewer', fn (Builder $reviewer) => $reviewer->where('user_id', $user->id));
    }

    /** @return list<UserSubmissionRowData> pengajuan induk sebagai ketua atau anggota. */
    private function submissions(User $user, bool $managesSubmissions): array
    {
        $profileId = $user->userProfile?->id;

        return array_values(FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->where(fn (Builder $q) => $q
                ->where('submitted_by', $user->id)
                ->when($profileId !== null, fn (Builder $any) => $any->orWhereHas('researchMembers', fn (Builder $m) => $m->where('user_profile_id', $profileId))))
            ->with(['form:id,title', 'researchMembers' => fn (Relation $m) => $m->where('user_profile_id', $profileId)])
            ->latest('id')
            ->get()
            ->map(fn (FormSubmission $s): UserSubmissionRowData => UserSubmissionRowData::fromModel($s, $user, $managesSubmissions))
            ->all());
    }
}
