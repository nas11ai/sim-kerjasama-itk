<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Data\View\Submission\Manage\ManagedReviewerData;
use App\Data\View\Submission\Manage\OperatorAbilitiesData;
use App\Data\View\Submission\Manage\OverrideTargetData;
use App\Data\View\Submission\Manage\OwnershipCandidateData;
use App\Data\View\Submission\Revision\ReviewThreadData;
use App\Data\View\Submission\SubmissionAnswerData;
use App\Data\View\Submission\SubmissionBudgetData;
use App\Data\View\Submission\SubmissionDecisionData;
use App\Data\View\Submission\SubmissionDetailData;
use App\Data\View\Submission\SubmissionHistoryEntryData;
use App\Data\View\Submission\SubmissionMemberData;
use App\Data\View\Submission\SubmissionTargetOutputData;
use App\Data\View\Submission\TargetOutputChangeData;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Review\ManagedReviewers;
use App\Queries\Review\ReviewThreads;
use App\Queries\Review\SubmittedEvaluationSheets;
use App\Support\Review\MinimumReviewers;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-02-02 — isi detail peneliti tanpa pilihan target luaran (Operator tidak
 * menyuntingnya), ditambah panel reviewer, lembar evaluasi (W22), ulir, dan aksi Operator.
 * Tanpa `canCreateThread`: Operator bukan reviewer bertugas (BR-REV-15).
 *
 * @phpstan-import-type PhaseAccess from ResearcherSubmissionDetail
 * @phpstan-import-type Score from SubmittedEvaluationSheets
 *
 * @phpstan-type OperatorExtras array{organization: string|null, reviewers: list<ManagedReviewerData>, activeReviewerCount: int, minimumReviewers: int, needsReview: bool, evaluationSheets: list<Score>, threads: list<ReviewThreadData>, ownershipCandidates: list<OwnershipCandidateData>, overrideTarget: OverrideTargetData, operatorCan: OperatorAbilitiesData}
 * @phpstan-type ManageDetail array{submission: SubmissionDetailData, answers: list<SubmissionAnswerData>, members: list<SubmissionMemberData>, targetOutputs: list<SubmissionTargetOutputData>, targetHistory: list<TargetOutputChangeData>, budget: SubmissionBudgetData, history: list<SubmissionHistoryEntryData>, decision: SubmissionDecisionData|null, scores: array{visible: bool, reason: string|null, scores: list<Score>}, phaseAccess: PhaseAccess, printableDocuments: list<array{value: string, label: string}>, can: SubmissionAbilitiesData, organization: string|null, reviewers: list<ManagedReviewerData>, activeReviewerCount: int, minimumReviewers: int, needsReview: bool, evaluationSheets: list<Score>, threads: list<ReviewThreadData>, ownershipCandidates: list<OwnershipCandidateData>, overrideTarget: OverrideTargetData, operatorCan: OperatorAbilitiesData}
 *
 * @method static ManageDetail run(User $viewer, FormSubmission $submission)
 */
class OperatorSubmissionDetail implements ChangesNoState
{
    use AsAction;

    /** @return ManageDetail */
    public function handle(User $viewer, FormSubmission $submission): array
    {
        $detail = ResearcherSubmissionDetail::run($viewer, $submission);
        unset($detail['targetOutputTypes']);

        return [...$detail, ...$this->extras($viewer, $submission)];
    }

    /** @return OperatorExtras */
    private function extras(User $viewer, FormSubmission $submission): array
    {
        $reviewers = ManagedReviewers::run($submission);
        $threads = ReviewThreads::run($submission);
        $phaseDetail = $submission->getFormPhaseDetail();

        return [
            'organization' => $submission->submittedBy->organization?->name,
            'reviewers' => $reviewers,
            // BR-REV-05 — hanya yang aktif dihitung; yang digantikan tinggal sejarah.
            'activeReviewerCount' => count(array_filter($reviewers, fn (ManagedReviewerData $r): bool => !$r->replaced)),
            'minimumReviewers' => MinimumReviewers::for($submission),
            'needsReview' => $phaseDetail !== null && $phaseDetail->needs_review,
            'evaluationSheets' => SubmittedEvaluationSheets::run($submission, true),
            'threads' => ReviewThreadData::many($threads['summaries'], $threads['comments']),
            'ownershipCandidates' => OwnershipCandidates::run($submission),
            'overrideTarget' => new OverrideTargetData($this->phaseLabel($phaseDetail), $this->overdueReviewers($submission)),
            'operatorCan' => OperatorAbilitiesData::forUser($viewer, $submission),
        ];
    }

    private function phaseLabel(?FormPhaseDetail $detail): ?string
    {
        if ($detail === null) {
            return null;
        }

        $detail->loadMissing(['formPhase:id,title', 'submissionDate.submissionDateLabel:id,name']);
        $parts = array_filter([$detail->formPhase?->title, $detail->submissionDate->submissionDateLabel?->name]);

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /** @return list<string> reviewer aktif yang punya form evaluasi lewat tenggat */
    private function overdueReviewers(FormSubmission $submission): array
    {
        $names = [];
        $seats = $submission->activeSubmissionReviewers()
            ->with(['reviewer.user:id,name', 'reviewerFormAssignments.reviewFormResponse:id,reviewer_form_assignment_id,status'])
            ->orderBy('id')
            ->get();

        foreach ($seats as $seat) {
            if ($this->hasOverdueForm($seat)) {
                $names[] = $seat->reviewer->user->name ?? 'Reviewer';
            }
        }

        return $names;
    }

    private function hasOverdueForm(SubmissionReviewer $seat): bool
    {
        return $seat->reviewerFormAssignments->contains(fn (ReviewerFormAssignment $form): bool => $form->isOverdue());
    }
}
