<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Queries\Review\AssignedReviewerCards;
use App\Queries\Review\ReviewersWithinTenure;
use App\Queries\Review\ReviewThreads;
use App\States\Submission\SubmissionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi layar detail pengajuan bagi Operator. Route-nya mensyaratkan `users.manage`, jadi
 * penontonnya selalu admin: ia tidak pernah reviewer yang bertugas (BR-REV-15) dan tidak
 * membuka ulir.
 *
 * @phpstan-import-type ThreadStats from ReviewThreads
 * @phpstan-import-type ReviewerCard from AssignedReviewerCards
 *
 * @phpstan-type Person array{id: int|null, name: string|null, email: string|null}
 * @phpstan-type SubmissionCard array{id: int, is_submitted: bool, status: SubmissionStatus|null, created_at: CarbonInterface|null, updated_at: CarbonInterface|null, submitted_by: User|null, form: Form|null, review_summaries: Collection<int, ReviewSummary>, review_comments: Collection<int, ReviewComment>, assigned_reviewers: list<ReviewerCard>}
 * @phpstan-type View array{submission: SubmissionCard, responses: array<int, string|null>, reviewStats: ThreadStats, availableReviewers: list<array{id: int, name: string|null, email: string|null, role: string}>, canAssignReviewers: bool, canReject: bool, canWithdraw: bool, canTransferOwnership: bool, ownershipCandidates: list<Person>, canReview: bool, canCreateThread: bool, hasPendingEvaluations: bool, pendingEvaluationsCount: int, hasReviewEvaluationForms: bool, userRole: string, assignedReviewers: list<ReviewerCard>, reviewerFormAssignments: list<never>, submissionStatus: SubmissionStatus|null}
 *
 * @method static View run(User $user, FormSubmission $submission)
 */
class OperatorSubmissionView implements ChangesNoState
{
    use AsAction;

    /** @return View */
    public function handle(User $user, FormSubmission $submission): array
    {
        $responses = SubmissionAnswers::run($submission);
        $threads = ReviewThreads::run($submission);
        $reviewers = AssignedReviewerCards::run($submission);

        return [
            'submission' => [
                'id' => $submission->id,
                'is_submitted' => $submission->is_submitted,
                'status' => $submission->status,
                'created_at' => $submission->created_at,
                'updated_at' => $submission->updated_at,
                'submitted_by' => $submission->submittedBy,
                'form' => $submission->form,
                'review_summaries' => $threads['summaries'],
                'review_comments' => $threads['comments'],
                'assigned_reviewers' => $reviewers,
            ],
            'responses' => $responses,
            'reviewStats' => $threads['stats'],
            'availableReviewers' => $this->availableReviewers($submission),
            'canAssignReviewers' => $user->can('users.manage'),
            // Policy yang memutuskan, bukan permission mentah — supaya tombol tidak muncul untuk
            // aksi yang pasti ditolak (ADR 0015).
            'canReject' => $user->can('reject', $submission),
            'canWithdraw' => $user->can('withdraw', $submission),
            'canTransferOwnership' => $user->can('submissions.view-all'),
            'ownershipCandidates' => $this->ownershipCandidates($submission),
            'canReview' => true,
            'canCreateThread' => false,
            'hasPendingEvaluations' => false,
            'pendingEvaluationsCount' => 0,
            'hasReviewEvaluationForms' => $submission->formPhaseDetail?->hasReviewEvaluationForms() ?? false,
            'userRole' => 'admin',
            'assignedReviewers' => $reviewers,
            'reviewerFormAssignments' => [],
            'submissionStatus' => $submission->status,
        ];
    }

    /**
     * BR-REV-09 — hanya reviewer dalam masa tugas; yang pernah ditugaskan di sini dan pengaju
     * sendiri tidak ditawarkan.
     *
     * @return list<array{id: int, name: string|null, email: string|null, role: string}>
     */
    private function availableReviewers(FormSubmission $submission): array
    {
        return array_values(ReviewersWithinTenure::run()
            ->with('user')
            ->whereNotIn('id', $submission->submissionReviewers()->select('reviewer_id'))
            ->whereHas('user', fn (Builder $q) => $q->where('id', '!=', $submission->submitted_by))
            ->get()
            ->map(fn (Reviewer $reviewer): array => [
                'id' => $reviewer->id,
                'name' => $reviewer->user?->name,
                'email' => $reviewer->user?->email,
                'role' => $reviewer->reviewer_type,
            ])
            ->all());
    }

    /**
     * BR-SM-09 — hanya co_investigator aktif yang boleh menerima kepemilikan; disaring di sini
     * supaya UI tidak menawarkan orang yang pasti ditolak Action.
     *
     * @return list<Person>
     */
    private function ownershipCandidates(FormSubmission $submission): array
    {
        return array_values($submission->researchMembers()
            ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
            ->where('status', 'active')
            ->with('userProfile.user')
            ->get()
            ->map(fn (ResearchMember $m): array => [
                'id' => $m->userProfile->user?->id,
                'name' => $m->userProfile->user?->name,
                'email' => $m->userProfile->user?->email,
            ])
            ->filter(fn (array $candidate): bool => $candidate['id'] !== null)
            ->all());
    }
}
