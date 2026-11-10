<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Data\View\ResearchOutput\OutputRowData;
use App\Data\View\ResearchOutput\OutputWriteAccessData;
use App\Data\View\Review\DecisionReadinessData;
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
use App\Queries\ResearchOutput\OutputRows;
use App\Queries\ResearchOutput\OutputUploadDetail;
use App\Queries\ResearchOutput\OutputWriteAccess;
use App\Queries\Review\DecisionReadiness;
use App\Queries\Review\ManagedReviewers;
use App\Queries\Review\ReviewThreads;
use App\Queries\Review\SubmittedEvaluationSheets;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use App\Support\PhaseAccessGate;
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
 * @phpstan-type OutputExtras array{outputs: list<OutputRowData>|null, outputAccess: OutputWriteAccessData|null, outputPhaseAccess: PhaseAccess|null, outputOverrideTarget: OverrideTargetData|null}
 * @phpstan-type OperatorExtras array{organization: string|null, reviewers: list<ManagedReviewerData>, activeReviewerCount: int, minimumReviewers: int, needsReview: bool, evaluationSheets: list<Score>, threads: list<ReviewThreadData>, ownershipCandidates: list<OwnershipCandidateData>, overrideTarget: OverrideTargetData, operatorCan: OperatorAbilitiesData, decisionReadiness: DecisionReadinessData}
 * @phpstan-type ManageDetail array{submission: SubmissionDetailData, answers: list<SubmissionAnswerData>, members: list<SubmissionMemberData>, targetOutputs: list<SubmissionTargetOutputData>, targetHistory: list<TargetOutputChangeData>, budget: SubmissionBudgetData, history: list<SubmissionHistoryEntryData>, decision: SubmissionDecisionData|null, scores: array{visible: bool, reason: string|null, scores: list<Score>}, phaseAccess: PhaseAccess, printableDocuments: list<array{value: string, label: string}>, can: SubmissionAbilitiesData, organization: string|null, reviewers: list<ManagedReviewerData>, activeReviewerCount: int, minimumReviewers: int, needsReview: bool, evaluationSheets: list<Score>, threads: list<ReviewThreadData>, ownershipCandidates: list<OwnershipCandidateData>, overrideTarget: OverrideTargetData, operatorCan: OperatorAbilitiesData, decisionReadiness: DecisionReadinessData, outputs: list<OutputRowData>|null, outputAccess: OutputWriteAccessData|null, outputPhaseAccess: PhaseAccess|null, outputOverrideTarget: OverrideTargetData|null}
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

        return [...$detail, ...$this->extras($viewer, $submission), ...self::outputExtras($viewer, $submission)];
    }

    /** @return OperatorExtras */
    private function extras(User $viewer, FormSubmission $submission): array
    {
        $reviewers = ManagedReviewers::run($submission);
        $threads = ReviewThreads::run($submission);
        $phaseDetail = $submission->getFormPhaseDetail();
        $readiness = DecisionReadiness::run($submission);

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
            'overrideTarget' => self::overrideTargetFor($submission, $phaseDetail),
            'operatorCan' => OperatorAbilitiesData::forUser($viewer, $submission, $readiness),
            // BR-REV-05, BR-REV-07 — kesiapan keputusan; `decision` sudah dipakai "Diputuskan oleh".
            'decisionReadiness' => $readiness,
        ];
    }

    /**
     * W144 — tab "Luaran": baris luaran, hak tulis Operator, dan perpanjangan Batas Akhir detail
     * Upload Luaran (RO-OUTPUT-16). Hanya APPROVED dan WITHDRAWN yang bisa punya luaran (BR-RO-01).
     *
     * @return OutputExtras
     */
    private static function outputExtras(User $viewer, FormSubmission $submission): array
    {
        if (!$submission->status instanceof Approved && !$submission->status instanceof Withdrawn) {
            return ['outputs' => null, 'outputAccess' => null, 'outputPhaseAccess' => null, 'outputOverrideTarget' => null];
        }

        $detail = OutputUploadDetail::for($submission);

        return [
            'outputs' => OutputRows::forSubmission($submission),
            'outputAccess' => OutputWriteAccess::for($viewer, $submission),
            'outputPhaseAccess' => $detail === null ? null : PhaseAccessGate::presentFor($submission, $detail, true),
            'outputOverrideTarget' => $detail === null ? null : self::overrideTargetFor($submission, $detail),
        ];
    }

    /** OPERATOR-02-03 — detail mana pun milik pengajuan: pengajuan, laporan monev (OPERATOR-05-02), Upload Luaran. */
    public static function overrideTargetFor(FormSubmission $submission, ?FormPhaseDetail $detail): OverrideTargetData
    {
        return new OverrideTargetData(self::phaseLabel($detail), self::overdueReviewers($submission, $detail?->id));
    }

    private static function phaseLabel(?FormPhaseDetail $detail): ?string
    {
        if ($detail === null) {
            return null;
        }

        $detail->loadMissing(['formPhase:id,title', 'submissionDate']);
        $parts = array_filter([$detail->formPhase?->title, $detail->submissionDate->label()]);

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /** @return list<string> reviewer aktif yang evaluasinya lewat tenggat dan dibuka override detail ini (W25) */
    private static function overdueReviewers(FormSubmission $submission, ?int $detailId): array
    {
        $names = [];
        $seats = $submission->activeSubmissionReviewers()
            ->with(['reviewer.user:id,name', 'reviewerFormAssignments.reviewFormResponse:id,reviewer_form_assignment_id,status',
                'reviewerFormAssignments.reviewEvaluationForm:id,form_phase_detail_id'])
            ->orderBy('id')
            ->get();

        foreach ($seats as $seat) {
            if (self::hasOverdueForm($seat, $detailId)) {
                $names[] = $seat->reviewer->user->name ?? 'Reviewer';
            }
        }

        return $names;
    }

    // BR-FE-10 — aturan yang sama dengan isClosedByDeadline(): override detail ini hanya membuka form evaluasi yang melekat padanya.
    private static function hasOverdueForm(SubmissionReviewer $seat, ?int $detailId): bool
    {
        return $seat->reviewerFormAssignments->contains(
            fn (ReviewerFormAssignment $form): bool => $form->isOverdue()
                && $form->reviewEvaluationForm->form_phase_detail_id === $detailId
        );
    }
}
