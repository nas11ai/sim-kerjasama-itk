<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Manage\ReviewerCandidateData;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Support\ConflictOfInterestGuard;
use App\Support\ReviewerWorkload;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Kandidat reviewer untuk satu pengajuan. Yang di luar masa tugas (BR-REV-09) atau akunnya
 * nonaktif (BR-IAM-05) tidak ditawarkan; konflik dan duplikat ditandai, bukan disembunyikan.
 *
 * @method static list<ReviewerCandidateData> run(FormSubmission $submission, string $search)
 */
class ReviewerCandidates implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 20;

    /** @return list<ReviewerCandidateData> */
    public function handle(FormSubmission $submission, string $search): array
    {
        $limit = ReviewerWorkload::limitFor($submission);
        $conflicting = ConflictOfInterestGuard::conflictingUserIds($submission);
        // BR-REV-03 — baris penugasan apa pun statusnya, sama dengan guard AssignReviewer.
        $assigned = array_values($submission->submissionReviewers()->get(['id', 'reviewer_id'])
            ->map(fn (SubmissionReviewer $seat): int => $seat->reviewer_id)
            ->all());

        $candidates = [];
        foreach ($this->reviewers($search) as $reviewer) {
            $load = $reviewer->getAttribute('active_load');
            $activeLoad = is_int($load) ? $load : 0;

            $candidates[] = new ReviewerCandidateData(
                reviewerId: $reviewer->id,
                name: $reviewer->user->name ?? 'Reviewer',
                nidn: $reviewer->user?->userProfile?->nidn,
                type: $reviewer->reviewer_type,
                activeLoad: $activeLoad,
                overloaded: $limit !== null && $activeLoad >= $limit,
                blockedReason: $this->blockedReason($reviewer, $assigned, $conflicting),
            );
        }

        return $candidates;
    }

    /** @return iterable<Reviewer> */
    private function reviewers(string $search): iterable
    {
        return ReviewersWithinTenure::run()
            ->whereHas('user', fn (Builder $user) => $user
                ->where('is_active', true)
                // PostgreSQL — whereLike tidak peka huruf, nama atau NIDN.
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $any) => $any
                    ->whereLike('name', "%{$search}%")
                    ->orWhereHas('userProfile', fn (Builder $profile) => $profile->whereLike('nidn', "%{$search}%")))))
            ->with(['user:id,name', 'user.userProfile:id,user_id,nidn'])
            ->withCount(['submissionReviewers as active_load' => fn (Builder $q) => $q->where('status', 'active')])
            ->orderBy(User::query()->select('name')->whereColumn('users.id', 'reviewers.user_id'))
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * @param  list<int>  $assigned
     * @param  list<int>  $conflicting
     */
    private function blockedReason(Reviewer $reviewer, array $assigned, array $conflicting): ?string
    {
        // Duplikat dicek dulu: reviewer yang sudah bertugas juga "berperan", tapi alasannya lain.
        if (in_array($reviewer->id, $assigned, true)) {
            return 'Sudah ditugaskan pada siklus ini';
        }

        return in_array($reviewer->user_id, $conflicting, true) ? 'Konflik kepentingan' : null;
    }
}
